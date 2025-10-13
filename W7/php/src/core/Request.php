<?php
	
	class Request
	{
		private array $data;
		private string $method;
		private string $path;
		
		public function __construct()
		{
			$this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
			$this->path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
			$this->data = $this->parseInput();
		}
		
		public function getMethod(): string
		{
			return $this->method;
		}
		
		public function getPath(): string
		{
			return $this->path;
		}
		
		public function getData(): array
		{
			return $this->data;
		}
		
		public function get(string $key, $default = null)
		{
			return $this->data[$key] ?? $default;
		}
		
		public function getCookie(string $name, $default = null)
		{
			return $_COOKIE[$name] ?? $default;
		}
		
		public function getFile(string $name): ?array
		{
			return $_FILES[$name] ?? null;
		}
		
		private function parseInput(): array
		{
			if ($this->method === 'GET') {
				return $_GET;
			}
			
			if ($this->method === 'POST' && !empty($_POST)) {
				return $_POST;
			}
			
			// Для JSON запросов
			$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
			if (str_contains($contentType, 'application/json')) {
				$json = file_get_contents('php://input');
				$decoded = json_decode($json, true);
				return is_array($decoded) ? $decoded : [];
			}
			
			return [];
		}
		
		public function validate(array $rules): array
		{
			$errors = [];
			
			foreach ($rules as $field => $rule) {
				$value = $this->get($field);
				
				if (isset($rule['required']) && $rule['required'] && empty($value)) {
					$errors[$field] = "Поле $field обязательно для заполнения";
					continue;
				}
				
				if (!empty($value)) {
					if (isset($rule['min_length']) && strlen($value) < $rule['min_length']) {
						$errors[$field] = "Поле $field должно содержать минимум {$rule['min_length']} символов";
					}
					
					if (isset($rule['max_length']) && strlen($value) > $rule['max_length']) {
						$errors[$field] = "Поле $field должно содержать максимум {$rule['max_length']} символов";
					}
					
					if (isset($rule['email']) && $rule['email'] && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
						$errors[$field] = "Поле $field должно быть валидным email";
					}
				}
			}
			
			return $errors;
		}
	}

