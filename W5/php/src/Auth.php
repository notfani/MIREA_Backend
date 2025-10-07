<?php
	require_once __DIR__ . '/DB.php';
	
	class Auth
	{
		static function register()
		{
			$d = json_decode(file_get_contents('php://input'), true);
			$hash = password_hash($d['pwd'], PASSWORD_DEFAULT);
			$db = DB::get();
			$st = $db->prepare("INSERT INTO users(login,pwd_hash) VALUES (?,?)");
			$st->execute([$d['login'], $hash]);
			return json_encode(['ok' => true]);
		}
		
		static function login()
		{
			$d = json_decode(file_get_contents('php://input'), true);
			$db = DB::get();
			$st = $db->prepare("SELECT * FROM users WHERE login=?");
			$st->execute([$d['login']]);
			$u = $st->fetch();
			if ($u && password_verify($d['pwd'], $u['pwd_hash'])) {
				setcookie('uid', $u['id'], 0, '/');
				setcookie('theme', $u['theme'], 0, '/');
				setcookie('lang', $u['lang'], 0, '/');
				return json_encode(['ok' => true]);
			}
			http_response_code(401);
			return json_encode(['ok' => false]);
		}
		
		static function logout()
		{
			// Очистка куков
			foreach (['uid', 'theme', 'lang'] as $c) {
				setcookie($c, '', time() - 3600, '/');
			}
			return json_encode(['ok' => true]);
		}
	}
