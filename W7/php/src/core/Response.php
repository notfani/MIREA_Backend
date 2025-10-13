<?php
	
	/**
	 * Класс для работы с HTTP ответами
	 */
	class Response
	{
		/**
		 * Отправить JSON ответ
		 */
		public static function json(array $data, int $statusCode = 200): string
		{
			http_response_code($statusCode);
			header('Content-Type: application/json; charset=utf-8');
			return json_encode($data, JSON_UNESCAPED_UNICODE);
		}
		
		/**
		 * Отправить успешный JSON ответ
		 */
		public static function success($data = null, string $message = ''): string
		{
			$response = ['ok' => true];
			if ($message) {
				$response['message'] = $message;
			}
			if ($data !== null) {
				$response['data'] = $data;
			}
			return self::json($response);
		}
		
		/**
		 * Отправить ошибку JSON
		 */
		public static function error(string $message, int $statusCode = 400, array $details = []): string
		{
			$response = [
				'ok' => false,
				'error' => $message
			];
			if (!empty($details)) {
				$response['details'] = $details;
			}
			return self::json($response, $statusCode);
		}
		
		/**
		 * Редирект
		 */
		public static function redirect(string $url, int $statusCode = 302): void
		{
			header("Location: $url", true, $statusCode);
			exit;
		}
	}

