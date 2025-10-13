<?php
	
	class Content
	{
		private UserRepository $userRepository;
		private array $config;
		
		public function __construct()
		{
			$this->userRepository = new UserRepository();
			$this->config = require __DIR__ . '/config/config.php';
		}
		
		public function personal(Request $request): string
		{
			try {
				$uid = (int)$request->getCookie('uid', 0);
				$theme = $request->getCookie('theme', 'light');
				$lang = $request->getCookie('lang', 'ru');
				
				// Попытка получить из кеша
				try {
					$redis = RedisClient::get();
					$cacheKey = "content:$uid:$theme:$lang";
					$cached = $redis->get($cacheKey);
					
					if ($cached) {
						Logger::getInstance()->debug('Content served from cache', [
							'userId' => $uid,
							'theme' => $theme,
							'lang' => $lang
						]);
						return $cached;
					}
				} catch (RuntimeException $e) {
					// Redis недоступен, продолжаем без кеша
					Logger::getInstance()->warning('Redis unavailable, serving without cache', [
						'error' => $e->getMessage()
					]);
				}
				
				// Генерация контента
				$greeting = $this->getGreeting($lang);
				$banner = $this->getBanner($theme);
				
				$content = [
					'greeting' => $greeting,
					'theme' => $theme,
					'banner' => $banner,
					'userId' => $uid
				];
				
				$response = Response::success($content);
				
				// Сохранение в кеш
				try {
					$redis = RedisClient::get();
					$cacheKey = "content:$uid:$theme:$lang";
					$redis->setex($cacheKey, $this->config['cache']['ttl'], $response);
					
					Logger::getInstance()->debug('Content cached', [
						'userId' => $uid,
						'theme' => $theme,
						'lang' => $lang
					]);
				} catch (RuntimeException $e) {
					// Игнорируем ошибки кеширования
				}
				
				return $response;
				
			} catch (Throwable $e) {
				Logger::getInstance()->error('Content error', ['error' => $e->getMessage()]);
				return Response::error('Внутренняя ошибка сервера', 500);
			}
		}
		
		/**
		 * Получить приветствие на нужном языке
		 */
		private function getGreeting(string $lang): string
		{
			$greetings = [
				'ru' => 'Привет',
				'en' => 'Hello',
				'es' => 'Hola',
				'fr' => 'Bonjour',
				'de' => 'Hallo',
			];
			
			return $greetings[$lang] ?? $greetings['ru'];
		}
		
		/**
		 * Получить баннер в зависимости от темы
		 */
		private function getBanner(string $theme): string
		{
			$banners = [
				'light' => '/static/light.svg',
				'dark' => '/static/dark.svg',
				'colorblind' => '/static/cb.svg',
			];
			
			return $banners[$theme] ?? $banners['light'];
		}
		
		/**
		 * Очистить кеш для пользователя
		 */
		public function clearUserCache(int $userId): void
		{
			try {
				$redis = RedisClient::get();
				$patterns = [
					"content:$userId:light:*",
					"content:$userId:dark:*",
					"content:$userId:colorblind:*",
				];
				
				foreach ($patterns as $pattern) {
					$keys = $redis->keys($pattern);
					if (!empty($keys)) {
						$redis->del($keys);
					}
				}
				
				Logger::getInstance()->info('User cache cleared', ['userId' => $userId]);
			} catch (RuntimeException $e) {
				Logger::getInstance()->warning('Failed to clear user cache', [
					'userId' => $userId,
					'error' => $e->getMessage()
				]);
			}
		}
	}
