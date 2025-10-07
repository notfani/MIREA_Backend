<?php
	
	class PdfService
	{
		static function upload()
		{
			if (!isset($_COOKIE['uid'])) {
				http_response_code(401);
				return json_encode(['ok' => false, 'error' => 'Unauthorized']);
			}
			$uid = $_COOKIE['uid'];
			if (!isset($_FILES['pdf'])) {
				http_response_code(400);
				return json_encode(['ok' => false, 'error' => 'No file uploaded']);
			}
			$f = $_FILES['pdf'];
			if ($f['type'] !== 'application/pdf') {
				http_response_code(400);
				return json_encode(['ok' => false, 'error' => 'Only PDF files allowed']);
			}
			
			$originalName = $f['name']; // Сохраняем оригинальное название
			$hashedName = uniqid('', true) . '.pdf'; // Генерируем уникальное имя для диска
			
			$uploadsDir = __DIR__ . '/uploads';
			if (!is_dir($uploadsDir) && !mkdir($uploadsDir, 0775, true) && !is_dir($uploadsDir)) {
				throw new RuntimeException(sprintf('Directory "%s" was not created', $uploadsDir));
			}
			$dest = $uploadsDir . "/$hashedName";
			
			if (!move_uploaded_file($f['tmp_name'], $dest)) {
				http_response_code(500);
				return json_encode(['ok' => false, 'error' => 'Failed to save file']);
			}
			
			try {
				$db = DB::get();
				$st = $db->prepare("INSERT INTO pdfs(user_id, filename, original_name) VALUES (?, ?, ?)");
				$st->execute([$uid, $hashedName, $originalName]);
			} catch (Exception $e) {
				// Если не удалось сохранить в БД, удаляем файл
				unlink($dest);
				http_response_code(500);
				return json_encode(['ok' => false, 'error' => 'Database error']);
			}
			
			return json_encode(['ok' => true, 'message' => 'File uploaded successfully']);
		}
		
		static function download($id)
		{
			$db = DB::get();
			$st = $db->prepare("SELECT filename, original_name FROM pdfs WHERE id=?");
			$st->execute([$id]);
			$f = $st->fetch();
			if (!$f) {
				http_response_code(404);
				exit;
			}
			$path = __DIR__ . "/uploads/" . $f['filename'];
			if (!file_exists($path)) {
				http_response_code(404);
				exit;
			}
			header('Content-Type: application/pdf');
			header('Content-Disposition: inline; filename="' . addslashes($f['original_name']) . '"');
			readfile($path);
		}
		
		static function delete($id)
		{
			if (!isset($_COOKIE['uid'])) {
				http_response_code(401);
				return json_encode(['ok' => false, 'error' => 'Unauthorized']);
			}
			$uid = $_COOKIE['uid'];
			
			try {
				$db = DB::get();
				// Проверяем, что файл принадлежит пользователю
				$st = $db->prepare("SELECT filename FROM pdfs WHERE id=? AND user_id=?");
				$st->execute([$id, $uid]);
				$f = $st->fetch();
				
				if (!$f) {
					http_response_code(404);
					return json_encode(['ok' => false, 'error' => 'File not found']);
				}
				
				// Удаляем файл с диска
				$path = __DIR__ . "/uploads/" . $f['filename'];
				if (file_exists($path)) {
					unlink($path);
				}
				
				// Удаляем запись из БД
				$st = $db->prepare("DELETE FROM pdfs WHERE id=? AND user_id=?");
				$st->execute([$id, $uid]);
				
				return json_encode(['ok' => true, 'message' => 'File deleted successfully']);
			} catch (Exception $e) {
				http_response_code(500);
				return json_encode(['ok' => false, 'error' => 'Database error']);
			}
		}
	}