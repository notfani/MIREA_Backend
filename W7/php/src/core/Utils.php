<?php
	
	/**
	 * Утилиты для работы с приложением
	 */
	class Utils
	{
		/**
		 * Безопасный вывод HTML
		 */
		public static function e(?string $value): string
		{
			return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
		}
		
		/**
		 * Проверка CSRF токена
		 */
		public static function generateCsrfToken(): string
		{
			if (empty($_SESSION['csrf_token'])) {
				$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
			}
			return $_SESSION['csrf_token'];
		}
		
		/**
		 * Проверка CSRF токена
		 */
		public static function verifyCsrfToken(string $token): bool
		{
			return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
		}
		
		/**
		 * Форматирование размера файла
		 */
		public static function formatFileSize(int $bytes): string
		{
			$units = ['B', 'KB', 'MB', 'GB', 'TB'];
			$bytes = max($bytes, 0);
			$pow = floor(($bytes ? log($bytes) : 0) / log(1024));
			$pow = min($pow, count($units) - 1);
			$bytes /= (1 << (10 * $pow));
			
			return round($bytes, 2) . ' ' . $units[$pow];
		}
		
		/**
		 * Форматирование даты
		 */
		public static function formatDate(string $date, string $lang = 'ru'): string
		{
			$timestamp = strtotime($date);
			if ($timestamp === false) {
				return $date;
			}
			
			$formats = [
				'ru' => 'd.m.Y H:i',
				'en' => 'Y-m-d H:i',
			];
			
			return date($formats[$lang] ?? $formats['ru'], $timestamp);
		}
		
		/**
		 * Генерация случайного имени файла
		 */
		public static function generateUniqueFilename(string $extension = ''): string
		{
			return uniqid('', true) . ($extension ? '.' . ltrim($extension, '.') : '');
		}
		
		/**
		 * Проверка mime-type файла
		 */
		public static function verifyFileMimeType(string $filePath, array $allowedTypes): bool
		{
			if (!file_exists($filePath)) {
				return false;
			}
			
			$finfo = finfo_open(FILEINFO_MIME_TYPE);
			$mimeType = finfo_file($finfo, $filePath);
			finfo_close($finfo);
			
			return in_array($mimeType, $allowedTypes, true);
		}
		
		/**
		 * Санитизация имени файла
		 */
		public static function sanitizeFilename(string $filename): string
		{
			// Удаление опасных символов
			$filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
			// Ограничение длины
			$filename = mb_substr($filename, 0, 255);
			return $filename;
		}
	}

