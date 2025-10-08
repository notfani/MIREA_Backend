<?php
declare(strict_types=1);
require_once __DIR__ . '/vendor/autoload.php';

$dsn = "pgsql:host={$_ENV['POSTGRES_HOST']};dbname={$_ENV['POSTGRES_DB']}";
$pdo = new PDO($dsn, $_ENV['POSTGRES_USER'], $_ENV['POSTGRES_PASSWORD'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$pdo->exec("CREATE TABLE IF NOT EXISTS sales (
    id          SERIAL PRIMARY KEY,
    product     VARCHAR(100),
    qty         INT,
    price       NUMERIC(10,2),
    region      VARCHAR(50),
    sold_at     DATE
)");