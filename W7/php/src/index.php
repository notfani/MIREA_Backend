<?php
	/**
	 * Front controller
	 * Обрабатывает все входящие запросы и направляет их к нужным обработчикам
	 */
	
	require_once __DIR__ . '/_bootstrap.php';
	
	$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';

// Логирование запроса
	Logger::getInstance()->info('Request received', [
		'path' => $path,
		'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
		'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
	]);
	
	try {
		// API запросы передаём роутеру
		if (str_starts_with($path, '/api/')) {
			// Устанавливаем JSON заголовки для API
			header("Content-Type: application/json; charset=utf-8");
			require __DIR__ . '/api/router.php';
			exit; // Важно! Прерываем выполнение после API
		}
		
		// Приватная зона (только для авторизованных)
		if (str_starts_with($path, '/private')) {
			require __DIR__ . '/private/index.php';
			exit; // Прерываем выполнение после приватной зоны
		}
		
		// Корень сайта - страница входа/регистрации
		// Если пользователь уже авторизован, редирект в /private
		require __DIR__ . '/public/index.php';
		
	} catch (Throwable $e) {
		Logger::getInstance()->error('Fatal error in front controller', [
			'error' => $e->getMessage(),
			'trace' => $e->getTraceAsString()
		]);
		
		// Если это API запрос - возвращаем JSON ошибку
		if (str_starts_with($path, '/api/')) {
			http_response_code(500);
			echo json_encode([
				'ok' => false,
				'error' => 'Внутренняя ошибка сервера'
			]);
		} else {
			// Для обычных страниц - HTML ошибка
			http_response_code(500);
			echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Ошибка</title></head>';
			echo '<body><h1>Внутренняя ошибка сервера</h1><p>Попробуйте позже.</p></body></html>';
		}
	}
