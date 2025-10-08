<?php
// Do not display warnings/deprecations in the response body (they break JSON
// responses and header setting). Errors will still be logged to the container
// error log.
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);

require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/../ChartGenerator.php';

header('Content-Type: application/json');

$gen = new ChartGenerator($pdo, __DIR__ . '/../watermark.png');

// Generate charts (paths to temporary files)
$paths = [
    'bar'  => $gen->barByRegion(),
    'pie'  => $gen->pieByProduct(),
    'line' => $gen->lineByMonth(),
];

// Read the images and return base64 data URIs so clients can embed them
$charts = [];
foreach ($paths as $k => $p) {
    if (is_string($p) && file_exists($p) && is_readable($p)) {
        $data = file_get_contents($p);
        $charts[$k] = 'data:image/png;base64,' . base64_encode($data);
    } else {
        $charts[$k] = null;
    }
}

echo json_encode($charts, JSON_UNESCAPED_SLASHES);