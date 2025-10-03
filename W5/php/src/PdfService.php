<?php
	
	class PdfService
	{
		static function upload()
		{
			if (!isset($_COOKIE['uid'])) {
				http_response_code(401);
				exit;
			}
			$uid = $_COOKIE['uid'];
			if (!isset($_FILES['pdf'])) {
				http_response_code(400);
				exit;
			}
			$f = $_FILES['pdf'];
			if ($f['type'] !== 'application/pdf') {
				http_response_code(400);
				exit;
			}
			$name = uniqid('', true) . '.pdf';
			$uploadsDir = __DIR__ . '/uploads';
			if (!is_dir($uploadsDir) && !mkdir($uploadsDir, 0775, true) && !is_dir($uploadsDir)) {
				throw new RuntimeException(sprintf('Directory "%s" was not created', $uploadsDir));
			}
			$dest = $uploadsDir . "/$name";
			move_uploaded_file($f['tmp_name'], $dest);
			$db = DB::get();
			$st = $db->prepare("INSERT INTO pdfs(user_id,filename) VALUES (?,?)");
			$st->execute([$uid, $name]);
			return json_encode(['ok' => true]);
		}
		
		static function download($id)
		{
			$db = DB::get();
			$st = $db->prepare("SELECT filename FROM pdfs WHERE id=?");
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
			header('Content-Disposition: inline; filename="file.pdf"');
			readfile($path);
		}
	}