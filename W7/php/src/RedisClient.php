<?php
	
	/**
	 * Класс для работы с Redis
	 * Использует паттерн Singleton
	 */
	final class RedisClient
	{
		private static ?Redis $redis = null;
		
		/**
		 * Получить Redis соединение
		 * @throws RuntimeException
		 */
		public static function get(): Redis
		{
			if (self::$redis === null) {
				self::connect();
			}
			return self::$redis;
		}
		
		/**
		 * Установить соединение с Redis
		 * @throws RuntimeException
		 */
		private static function connect(): void
		{
			try {
				$config = require __DIR__ . '/config/config.php';
				$redisConfig = $config['redis'];
				
				$redis = new Redis();
				$connected = $redis->connect(
					$redisConfig['host'],
					$redisConfig['port'],
					$redisConfig['timeout']
				);
				
				if (!$connected) {
					throw new RuntimeException('Failed to connect to Redis');
				}
				
				self::$redis = $redis;
				
				Logger::getInstance()->info('Redis connection established');
			} catch (Exception $e) {
				Logger::getInstance()->error('Redis connection failed', [
					'error' => $e->getMessage()
				]);
				throw new RuntimeException('Failed to connect to Redis: ' . $e->getMessage());
			}
		}
		
		/**
		 * Закрыть соединение
		 */
		public static function disconnect(): void
		{
			if (self::$redis !== null) {
				self::$redis->close();
				self::$redis = null;
			}
		}
	}
