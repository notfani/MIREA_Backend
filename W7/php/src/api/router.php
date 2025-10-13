<?php
	/**
	 * API Router - обрабатывает все API запросы
	 */

// Подключение зависимостей
	require_once __DIR__ . '/../_bootstrap.php';

// Инициализация
	$request = new Request();
	$method = $request->getMethod();
	$path = $request->getPath();
	
	header("Access-Control-Allow-Origin: *");
	header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
	header("Access-Control-Allow-Headers: Content-Type");

// Обработка preflight запросов
	if ($method === 'OPTIONS') {
		http_response_code(200);
		exit;
	}

// Логирование запроса
	Logger::getInstance()->info('API request', [
		'method' => $method,
		'path' => $path,
		'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
	]);
	
	try {
		// Маршрутизация
		switch (true) {
			// Аутентификация
			case $path === '/api/register' && $method === 'POST':
				$authService = new Auth();
				echo $authService->register($request);
				break;
			
			case $path === '/api/login' && $method === 'POST':
				$authService = new Auth();
				echo $authService->login($request);
				break;
			
			case $path === '/api/logout' && $method === 'POST':
				$authService = new Auth();
				echo $authService->logout($request);
				break;
			
			// Смена темы пользователя
			case $path === '/api/theme' && $method === 'POST':
				$uid = Auth::checkAuth($request);
				if (!$uid) {
					echo Response::error('Требуется авторизация', 401);
					break;
				}
				$theme = trim((string)$request->get('theme', ''));
				$allowed = ['light', 'dark', 'colorblind'];
				if (!in_array($theme, $allowed, true)) {
					echo Response::error('Неверное значение темы', 422, ['allowed' => $allowed]);
					break;
				}
				$repo = new UserRepository();
				$ok = $repo->updateTheme($uid, $theme);
				if (!$ok) {
					Logger::getInstance()->error('Failed to update theme', ['userId' => $uid, 'theme' => $theme]);
					echo Response::error('Не удалось сохранить тему', 500);
					break;
				}
				// Обновляем cookie
				$config = require __DIR__ . '/../config/config.php';
				setcookie('theme', $theme, $config['session']['cookie_lifetime'], $config['session']['cookie_path']);
				// Чистим кеш персонального контента
				try {
					(new Content())->clearUserCache($uid);
				} catch (Throwable $e) { /* ignore */
				}
				Logger::getInstance()->info('Theme updated', ['userId' => $uid, 'theme' => $theme]);
				echo Response::success(['theme' => $theme], 'Тема обновлена');
				break;
			
			// Контент
			case $path === '/api/content' && $method === 'GET':
				$contentService = new Content();
				echo $contentService->personal($request);
				break;
			
			// Работа с PDF
			case $path === '/api/upload' && $method === 'POST':
				$pdfService = new PdfService();
				echo $pdfService->upload($request);
				break;
			
			case preg_match('#^/api/pdf/(\d+)$#', $path, $matches) && $method === 'GET':
				$pdfService = new PdfService();
				$pdfService->download((int)$matches[1], $request);
				break;
			
			case preg_match('#^/api/pdf/(\d+)$#', $path, $matches) && $method === 'DELETE':
				$pdfService = new PdfService();
				echo $pdfService->delete((int)$matches[1], $request);
				break;
			
			case preg_match('#^/api/delete-pdf/(\d+)$#', $path, $matches) && $method === 'DELETE':
				$pdfService = new PdfService();
				echo $pdfService->delete((int)$matches[1], $request);
				break;
			
			// 404
			default:
				Logger::getInstance()->warning('API route not found', [
					'method' => $method,
					'path' => $path
				]);
				echo Response::error('Маршрут не найден', 404);
				break;
		}
		
	} catch (Throwable $e) {
		// Глобальная обработка ошибок
		Logger::getInstance()->error('Unhandled API error', [
			'method' => $method,
			'path' => $path,
			'error' => $e->getMessage(),
			'trace' => $e->getTraceAsString()
		]);
		
		echo Response::error('Внутренняя ошибка сервера', 500);
	}
