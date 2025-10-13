<?php
	/**
	 * Конфигурационный файл приложения
	 */
	
	return [
		'db' => [
			'host' => getenv('PG_HOST') ?: 'postgres',
			'port' => (int)(getenv('PG_PORT') ?: 5432),
			'database' => getenv('PG_DB') ?: 'contentgate',
			'username' => getenv('PG_USER') ?: 'gate',
			'password' => getenv('PG_PASSWORD') ?: 'gatepass',
		],
		'redis' => [
			'host' => getenv('REDIS_HOST') ?: 'redis',
			'port' => (int)(getenv('REDIS_PORT') ?: 6379),
		],
		'app' => [
			'debug' => getenv('APP_DEBUG') === 'true',
			'uploads_path' => '/var/www/html/uploads',
			'max_file_size' => 10 * 1024 * 1024, // 10MB
			'allowed_extensions' => ['pdf'],
		],
		'uploads' => [
			'directory' => '/var/www/html/uploads',
			'max_size' => 10 * 1024 * 1024, // 10MB
			'allowed_types' => ['application/pdf'],
		],
		'session' => [
			'name' => 'CONTENTGATE_SESSION',
			'lifetime' => 3600, // 1 hour
			'secure' => false, // set to true in production with HTTPS
			'httponly' => true,
			'samesite' => 'Lax',
			'cookie_lifetime' => time() + 86400, // 24 hours
			'cookie_path' => '/',
		],
		'security' => [
			'login_min_length' => 3,
			'password_min_length' => 4,
			'max_login_attempts' => 5,
			'lockout_time' => 900, // 15 minutes
		],
		'cache' => [
			'ttl' => 300, // 5 minutes
			'prefix' => 'contentgate:',
		],
	];
