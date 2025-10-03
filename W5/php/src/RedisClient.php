<?php
	
	final class RedisClient
	{
		private static ?Redis $r = null;
		
		public static function get(): Redis
		{
			if (self::$r === null) {
				$host = getenv('REDIS_HOST') ?: 'redis';
				$port = (int)(getenv('REDIS_PORT') ?: 6379);
				$r = new Redis();
				$r->connect($host, $port, 2.5);
				self::$r = $r;
			}
			return self::$r;
		}
	}

