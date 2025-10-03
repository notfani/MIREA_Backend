<?php
	
	final class DB
	{
		private static ?PDO $pdo = null;
		
		public static function get(): PDO
		{
			if (self::$pdo === null) {
				$host = getenv('PG_HOST') ?: 'postgres';
				$port = (int)(getenv('PG_PORT') ?: 5432);
				$db = getenv('PG_DB') ?: 'contentgate';
				$user = getenv('PG_USER') ?: 'gate';
				$pass = getenv('PG_PASSWORD') ?: 'gatepass';
				
				$dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s', $host, $port, $db);
				$pdo = new PDO($dsn, $user, $pass, [
					PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
					PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
				]);
				self::$pdo = $pdo;
			}
			return self::$pdo;
		}
	}

