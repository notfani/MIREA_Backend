<?php
	require_once __DIR__ . "/../DB.php";
	require_once __DIR__ . "/../RedisClient.php";
	require_once __DIR__ . "/../Auth.php";
	require_once __DIR__ . "/../Content.php";
	require_once __DIR__ . "/../PdfService.php";
	require_once __DIR__ . "/../SetTheme.php";
	
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
		case '/api/set-theme':
			if ($method === 'POST') {
				// Read JSON payload and validate
				$d = json_decode(file_get_contents('php://input'), true) ?: [];
				$theme = $d['theme'] ?? '';
				$allowed = ['light','dark','colorblind'];
				if (!in_array($theme, $allowed, true)) {
					http_response_code(400);
					echo json_encode(['ok' => false, 'error' => 'invalid theme']);
					break;
				}

				$uid = (int)($_COOKIE['uid'] ?? 0);
				if ($uid <= 0) {
					http_response_code(401);
					echo json_encode(['ok' => false, 'error' => 'not authenticated']);
					break;
				}

				try {
					$db = DB::get();
					$st = $db->prepare('UPDATE users SET theme = ? WHERE id = ?');
					$st->execute([$theme, $uid]);
					setcookie('theme', $theme, time() + 3600*24*365, '/');
					$redis = RedisClient::get();
					$pattern = "content:$uid:*";
					$it = null;
					while ($keys = $redis->scan($it, $pattern, 100)) {
						foreach ($keys as $k) $redis->del($k);
						if ($it === 0) break;
					}
					echo json_encode(['ok' => true]);
				} catch (Throwable $e) {
					http_response_code(500);
					echo json_encode(['ok' => false, 'error' => 'server error']);
				}
			} else http_response_code(405);
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
