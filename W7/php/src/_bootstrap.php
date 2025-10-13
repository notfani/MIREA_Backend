<?php
	/**
	 * Файл инициализации приложения
	 * Подключает все необходимые классы и настройки
	 */

// Настройка сессий
	ini_set('session.cookie_httponly', 1);
	ini_set('session.use_strict_mode', 1);

// Автозагрузка классов
	spl_autoload_register(function ($class) {
		$directories = [
			__DIR__ . '/core/',
			__DIR__ . '/repositories/',
			__DIR__ . '/',
		];
		
		foreach ($directories as $directory) {
			$file = $directory . $class . '.php';
			if (file_exists($file)) {
				require_once $file;
				return;
			}
		}
	});

// Обработчик ошибок
	set_error_handler(function ($severity, $message, $file, $line) {
		if (class_exists('Logger')) {
			Logger::getInstance()->error("PHP Error: $message", [
				'file' => $file,
				'line' => $line,
				'severity' => $severity
			]);
		}
		
		// В режиме разработки показываем ошибки
		$config = @include __DIR__ . '/config/config.php';
		if ($config && isset($config['app']['debug']) && $config['app']['debug']) {
			echo "Error: $message in $file on line $line\n";
		}
	});

// Обработчик исключений
	set_exception_handler(function ($exception) {
		if (class_exists('Logger')) {
			Logger::getInstance()->error("Uncaught exception: " . $exception->getMessage(), [
				'file' => $exception->getFile(),
				'line' => $exception->getLine(),
				'trace' => $exception->getTraceAsString()
			]);
		}
		
		// В режиме разработки показываем исключения
		$config = @include __DIR__ . '/config/config.php';
		if ($config && isset($config['app']['debug']) && $config['app']['debug']) {
			echo "Uncaught exception: " . $exception->getMessage() . "\n";
			echo "File: " . $exception->getFile() . " on line " . $exception->getLine() . "\n";
		} else {
			echo "Internal Server Error";
		}
	});

// Старт сессии
	if (session_status() === PHP_SESSION_NONE) {
		session_start();
	}
