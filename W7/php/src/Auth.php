<?php
	
	/**
	 * Сервис аутентификации пользователей
	 */
	class Auth
	{
		private UserRepository $userRepository;
		private array $config;
		
		public function __construct()
		{
			$this->userRepository = new UserRepository();
			$this->config = require __DIR__ . '/config/config.php';
		}
		
		/**
		 * Регистрация нового пользователя
		 */
		public function register(Request $request): string
		{
			try {
				// Валидация входных данных
				$errors = $request->validate([
					'login' => [
						'required' => true,
						'min_length' => $this->config['security']['login_min_length'],
						'max_length' => 40
					],
					'pwd' => [
						'required' => true,
						'min_length' => $this->config['security']['password_min_length'],
						'max_length' => 80
					]
				]);
				
				if (!empty($errors)) {
					return Response::error('Ошибка валидации', 400, $errors);
				}
				
				$login = trim($request->get('login'));
				$password = $request->get('pwd');
				
				// Проверка существования пользователя
				$existingUser = $this->userRepository->findByLogin($login);
				if ($existingUser) {
					return Response::error('Пользователь с таким логином уже существует', 409);
				}
				
				// Хеширование пароля
				$hash = password_hash($password, PASSWORD_DEFAULT);
				
				// Создание пользователя
				$this->userRepository->create($login, $hash);
				
				Logger::getInstance()->info('User registered successfully', ['login' => $login]);
				
				return Response::success(null, 'Пользователь успешно зарегистрирован');
				
			} catch (RuntimeException $e) {
				return Response::error($e->getMessage(), 500);
			} catch (Throwable $e) {
				Logger::getInstance()->error('Registration error', ['error' => $e->getMessage()]);
				return Response::error('Внутренняя ошибка сервера', 500);
			}
		}
		
		/**
		 * Вход пользователя
		 */
		public function login(Request $request): string
		{
			try {
				// Валидация входных данных
				$errors = $request->validate([
					'login' => ['required' => true],
					'pwd' => ['required' => true]
				]);
				
				if (!empty($errors)) {
					return Response::error('Ошибка валидации', 400, $errors);
				}
				
				$login = trim($request->get('login'));
				$password = $request->get('pwd');
				
				// Поиск пользователя
				$user = $this->userRepository->findByLogin($login);
				
				if (!$user || !password_verify($password, $user['pwd_hash'])) {
					Logger::getInstance()->warning('Failed login attempt', ['login' => $login]);
					return Response::error('Неверный логин или пароль', 401);
				}
				
				// Установка cookies
				$cookieConfig = $this->config['session'];
				setcookie('uid', (string)$user['id'], $cookieConfig['cookie_lifetime'], $cookieConfig['cookie_path']);
				setcookie('theme', $user['theme'] ?? 'light', $cookieConfig['cookie_lifetime'], $cookieConfig['cookie_path']);
				setcookie('lang', $user['lang'] ?? 'ru', $cookieConfig['cookie_lifetime'], $cookieConfig['cookie_path']);
				
				Logger::getInstance()->info('User logged in', [
					'userId' => $user['id'],
					'login' => $login
				]);
				
				return Response::success(['userId' => $user['id']], 'Вход выполнен успешно');
				
			} catch (RuntimeException $e) {
				return Response::error($e->getMessage(), 500);
			} catch (Throwable $e) {
				Logger::getInstance()->error('Login error', ['error' => $e->getMessage()]);
				return Response::error('Внутренняя ошибка сервера', 500);
			}
		}
		
		/**
		 * Выход пользователя
		 */
		public function logout(Request $request): string
		{
			try {
				$userId = $request->getCookie('uid');
				
				// Очистка cookies
				$cookieConfig = $this->config['session'];
				foreach (['uid', 'theme', 'lang'] as $cookieName) {
					setcookie($cookieName, '', time() - 3600, $cookieConfig['cookie_path']);
				}
				
				Logger::getInstance()->info('User logged out', ['userId' => $userId]);
				
				return Response::success(null, 'Выход выполнен успешно');
				
			} catch (Throwable $e) {
				Logger::getInstance()->error('Logout error', ['error' => $e->getMessage()]);
				return Response::error('Внутренняя ошибка сервера', 500);
			}
		}
		
		public static function checkAuth(Request $request): ?int
		{
			$uid = (int)$request->getCookie('uid', 0);
			return $uid > 0 ? $uid : null;
		}
	}
