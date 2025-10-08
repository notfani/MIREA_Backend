<?php
require_once __DIR__ . '/_bootstrap.php';

// Fetch charts manifest from the API. Use @ to suppress warnings from
// file_get_contents and check the result explicitly.
$json = @file_get_contents('http://localhost/api/charts.php');
$charts = null;
if ($json !== false) {
    // Decode as an object so we can use $charts->bar, $charts->pie, etc.
    $charts = json_decode($json);
}

// Helper to safely render a chart image. Returns an <img> tag when the
// chart path is available and readable, otherwise returns a friendly
// placeholder paragraph.
function render_chart_img($charts, string $key): string
{
    if (!is_object($charts) || empty($charts->{$key})) {
        return '<p>График недоступен.</p>';
    }

    $uri = $charts->{$key};
    if (!is_string($uri) || $uri === '') {
        return '<p>График недоступен.</p>';
    }

    // Assuming API returns a data URI like data:image/png;base64,...
    return '<img src="' . htmlspecialchars($uri, ENT_QUOTES, 'UTF-8') . '">';
}
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Статистика</title>
    <style>img{max-width:100%;margin:20px 0;border:1px solid #ccc}</style>
</head>
<body>
    <h1>Отчёты по продажам</h1>

    <h2>1. Столбчатая – по регионам</h2>
    <?= render_chart_img($charts, 'bar') ?>

    <h2>2. Круговая – топ товаров</h2>
    <?= render_chart_img($charts, 'pie') ?>

    <h2>3. Линейный график – динамика</h2>
    <?= render_chart_img($charts, 'line') ?>
</body>
</html>