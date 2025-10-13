<?php
	
	/**
	 * Простой класс для логирования
	 */
	class Logger
	{
		private static ?self $instance = null;
		private string $logFile;
		
		private function __construct()
		{
			$this->logFile = __DIR__ . '/../logs/app.log';
			$logDir = dirname($this->logFile);
			if (!is_dir($logDir)) {
				@mkdir($logDir, 0775, true);
			}
		}
		
		public static function getInstance(): self
		{
			if (self::$instance === null) {
				self::$instance = new self();
			}
			return self::$instance;
		}
		
		/**
		 * Записать сообщение в лог
		 */
		public function log(string $level, string $message, array $context = []): void
		{
			$timestamp = date('Y-m-d H:i:s');
			$contextStr = empty($context) ? '' : ' ' . json_encode($context, JSON_UNESCAPED_UNICODE);
			$logMessage = "[$timestamp] [$level] $message$contextStr" . PHP_EOL;
			
			@file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
		}
		
		public function error(string $message, array $context = []): void
		{
			$this->log('ERROR', $message, $context);
		}
		
		public function warning(string $message, array $context = []): void
		{
			$this->log('WARNING', $message, $context);
		}
		
		public function info(string $message, array $context = []): void
		{
			$this->log('INFO', $message, $context);
		}
		
		public function debug(string $message, array $context = []): void
		{
			$this->log('DEBUG', $message, $context);
		}
	}
