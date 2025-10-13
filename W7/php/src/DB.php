<?php
	
	/**
	 * Класс для работы с базой данных PostgreSQL
	 * Использует паттерн Singleton
	 */
	final class DB
	{
		private static ?PDO $pdo = null;
		
		/**
		 * Получить PDO соединение
		 * @throws RuntimeException
		 */
		public static function get(): PDO
		{
			if (self::$pdo === null) {
				self::connect();
			}
			return self::$pdo;
		}
		
		/**
		 * Установить соединение с БД
		 * @throws RuntimeException
		 */
		private static function connect(): void
		{
			try {
				$config = require __DIR__ . '/config/config.php';
				$dbConfig = $config['db'];
				
				$dsn = sprintf(
					'pgsql:host=%s;port=%d;dbname=%s',
					$dbConfig['host'],
					$dbConfig['port'],
					$dbConfig['database']
				);
				
				$pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
					PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
					PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
					PDO::ATTR_EMULATE_PREPARES => false,
				]);
				
				self::$pdo = $pdo;
				
				Logger::getInstance()->info('Database connection established');
			} catch (PDOException $e) {
				Logger::getInstance()->error('Database connection failed', [
					'error' => $e->getMessage()
				]);
				throw new RuntimeException('Failed to connect to database: ' . $e->getMessage());
			}
		}
		
		/**
		 * Закрыть соединение
		 */
		public static function disconnect(): void
		{
			self::$pdo = null;
		}
	}
