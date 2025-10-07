<?php
// Front controller
	$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';

// API запросы передаём роутеру
	if (str_starts_with($path, '/api/')) {
		require __DIR__ . '/api/router.php';
		return;
	}

// Корень сайта всегда показывает страницу входа/регистрации
	require __DIR__ . '/public/index.php';
