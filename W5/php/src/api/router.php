<?php
	require_once __DIR__ . "/../DB.php";
	require_once __DIR__ . "/../RedisClient.php";
	require_once __DIR__ . "/../Auth.php";
	require_once __DIR__ . "/../Content.php";
	require_once __DIR__ . "/../PdfService.php";
	
	$method = $_SERVER['REQUEST_METHOD'];
	$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
	
	// CORS для API
	if (str_starts_with($path, '/api')) {
		header("Access-Control-Allow-Origin: *");
		header("Content-Type: application/json");
	}
	
	switch ($path) {
		case '/api/register':
			if ($method === 'POST') echo Auth::register();
			else http_response_code(405);
			break;
		case '/api/login':
			if ($method === 'POST') echo Auth::login();
			else http_response_code(405);
			break;
		case '/api/logout':
			if ($method === 'POST') echo Auth::logout();
			else http_response_code(405);
			break;
		case '/api/content':
			if ($method === 'GET') echo Content::personal();
			else http_response_code(405);
			break;
		case '/api/upload':
			if ($method === 'POST') echo PdfService::upload();
			else http_response_code(405);
			break;
		case (preg_match('#^/api/pdf/(\d+)$#', $path, $m) ? true : false):
			if ($method === 'GET') PdfService::download((int)$m[1]);
			elseif ($method === 'DELETE') echo PdfService::delete((int)$m[1]);
			else http_response_code(405);
			break;
		case (preg_match('#^/api/delete-pdf/(\d+)$#', $path, $m) ? true : false):
			if ($method === 'DELETE') echo PdfService::delete((int)$m[1]);
			else http_response_code(405);
			break;
		default:
			// Заглушка для не-API запросов
			if (!str_starts_with($path, '/api')) {
				header('Content-Type: text/html; charset=utf-8');
				echo "<html lang=\"ru\"><head><title>MIREA W5</title></head><body><h1>Backend API</h1><p>Используйте эндпоинты /api/*</p></body></html>";
			} else {
				http_response_code(404);
				echo json_encode(['ok' => false, 'error' => 'Not found']);
			}
	}
