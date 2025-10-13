<?php
	
	class PdfService
	{
		private PdfRepository $pdfRepository;
		private array $config;
		
		public function __construct()
		{
			$this->pdfRepository = new PdfRepository();
			$this->config = require __DIR__ . '/config/config.php';
		}
		
		public function upload(Request $request): string
		{
			try {
				$uid = Auth::checkAuth($request);
				if (!$uid) {
					return Response::error('Требуется авторизация', 401);
				}
				
				$file = $request->getFile('pdf');
				if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
					return Response::error('Файл не загружен', 400);
				}
				
				if (!in_array($file['type'], $this->config['uploads']['allowed_types'])) {
					return Response::error('Разрешены только PDF файлы', 400);
				}
				
				if ($file['size'] > $this->config['uploads']['max_size']) {
					$maxSizeMb = $this->config['uploads']['max_size'] / 1024 / 1024;
					return Response::error("Максимальный размер файла: {$maxSizeMb}MB", 400);
				}
				
				$originalName = $file['name'];
				$hashedName = uniqid('', true) . '.pdf';
				
				// Создание директории для загрузок
				$uploadsDir = $this->config['uploads']['directory'];
				if (!is_dir($uploadsDir) && !mkdir($uploadsDir, 0775, true) && !is_dir($uploadsDir)) {
					throw new RuntimeException('Не удалось создать директорию для загрузок');
				}
				
				$destination = $uploadsDir . DIRECTORY_SEPARATOR . $hashedName;
				
				// Перемещение файла
				if (!move_uploaded_file($file['tmp_name'], $destination)) {
					throw new RuntimeException('Не удалось сохранить файл');
				}
				
				// Сохранение информации в БД
				try {
					$fileId = $this->pdfRepository->create($uid, $hashedName, $originalName);
					
					Logger::getInstance()->info('PDF uploaded successfully', [
						'fileId' => $fileId,
						'userId' => $uid,
						'originalName' => $originalName
					]);
					
					return Response::success([
						'id' => $fileId,
						'filename' => $originalName
					], 'Файл успешно загружен');
					
				} catch (RuntimeException $e) {
					// Удаляем файл если не удалось сохранить в БД
					@unlink($destination);
					throw $e;
				}
				
			} catch (RuntimeException $e) {
				return Response::error($e->getMessage(), 500);
			} catch (Throwable $e) {
				Logger::getInstance()->error('Upload error', ['error' => $e->getMessage()]);
				return Response::error('Внутренняя ошибка сервера', 500);
			}
		}
		
		public function download(int $id, Request $request): void
		{
			try {
				// Получение информации о файле
				$file = $this->pdfRepository->findById($id);
				
				if (!$file) {
					http_response_code(404);
					echo 'Файл не найден';
					return;
				}
				
				$uploadsDir = $this->config['uploads']['directory'];
				$filePath = $uploadsDir . DIRECTORY_SEPARATOR . $file['filename'];
				
				if (!file_exists($filePath)) {
					Logger::getInstance()->error('PDF file not found on disk', [
						'id' => $id,
						'path' => $filePath
					]);
					http_response_code(404);
					echo 'Файл не найден на диске';
					return;
				}
				
				// Отправка файла
				header('Content-Type: application/pdf');
				header('Content-Disposition: inline; filename="' . addslashes($file['original_name']) . '"');
				header('Content-Length: ' . filesize($filePath));
				
				readfile($filePath);
				
				Logger::getInstance()->info('PDF downloaded', [
					'id' => $id,
					'originalName' => $file['original_name']
				]);
				
			} catch (Throwable $e) {
				Logger::getInstance()->error('Download error', [
					'id' => $id,
					'error' => $e->getMessage()
				]);
				http_response_code(500);
				echo 'Ошибка при скачивании файла';
			}
		}
		
		public function delete(int $id, Request $request): string
		{
			try {
				$uid = Auth::checkAuth($request);
				if (!$uid) {
					return Response::error('Требуется авторизация', 401);
				}
				
				$file = $this->pdfRepository->findByIdAndUser($id, $uid);
				
				if (!$file) {
					return Response::error('Файл не найден', 404);
				}
				
				// Удаление файла с диска
				$uploadsDir = $this->config['uploads']['directory'];
				$filePath = $uploadsDir . DIRECTORY_SEPARATOR . $file['filename'];
				
				if (file_exists($filePath)) {
					@unlink($filePath);
				}
				
				// Удаление записи из БД
				$this->pdfRepository->delete($id, $uid);
				
				Logger::getInstance()->info('PDF deleted', [
					'id' => $id,
					'userId' => $uid,
					'originalName' => $file['original_name']
				]);
				
				return Response::success(null, 'Файл успешно удален');
				
			} catch (RuntimeException $e) {
				return Response::error($e->getMessage(), 500);
			} catch (Throwable $e) {
				Logger::getInstance()->error('Delete error', [
					'id' => $id,
					'error' => $e->getMessage()
				]);
				return Response::error('Внутренняя ошибка сервера', 500);
			}
		}
		
		public function getUserFiles(int $userId): array
		{
			try {
				return $this->pdfRepository->findByUserId($userId);
			} catch (RuntimeException $e) {
				Logger::getInstance()->error('Failed to get user files', [
					'userId' => $userId,
					'error' => $e->getMessage()
				]);
				return [];
			}
		}
	}
