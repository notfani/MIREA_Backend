<?php
declare(strict_types=1);

$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload)) {
	require_once $autoload;
}

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
	http_response_code(204);
	exit;
}

$storageFile = __DIR__ . '/../storage/clients.json';
ensureStorage($storageFile);
$clients = loadClients($storageFile);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$path = rtrim($path, '/');
if ($path === '') {
	$path = '/';
}

switch (true) {
	case $path === '/' && $method === 'GET':
		jsonResponse([
			'api' => '/api/clients',
			'documentation' => '/playground.html'
		]);
		break;

	case $path === '/api/clients' && $method === 'GET':
		jsonResponse(array_values($clients));
		break;

	case $path === '/api/clients' && $method === 'POST':
		$payload = parseJson();
		$client = createClient($clients, $payload);
		persistClients($storageFile, $clients);
		jsonResponse($client, 201);
		break;

	case preg_match('#^/api/clients/(\d+)$#', $path, $matches) === 1 && $method === 'GET':
		$id = (int) $matches[1];
		if (!isset($clients[$id])) {
			notFound();
		}
		jsonResponse($clients[$id]);
		break;

	case preg_match('#^/api/clients/(\d+)$#', $path, $matches) === 1 && in_array($method, ['PUT', 'PATCH'], true):
		$id = (int) $matches[1];
		if (!isset($clients[$id])) {
			notFound();
		}
		$payload = parseJson();
		$clients[$id] = updateClient($clients[$id], $payload);
		persistClients($storageFile, $clients);
		jsonResponse($clients[$id]);
		break;

	case preg_match('#^/api/clients/(\d+)$#', $path, $matches) === 1 && $method === 'DELETE':
		$id = (int) $matches[1];
		if (!isset($clients[$id])) {
			notFound();
		}
		unset($clients[$id]);
		persistClients($storageFile, $clients);
		http_response_code(204);
		exit;

	default:
		notFound();
}

function jsonResponse(array $data, int $status = 200): void
{
	http_response_code($status);
	header('Content-Type: application/json; charset=utf-8');
	echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
	exit;
}

function parseJson(): array
{
	$raw = file_get_contents('php://input');
	if ($raw === false || $raw === '') {
		jsonResponse(['error' => 'Не передано тело запроса'], 400);
	}
	$decoded = json_decode($raw, true);
	if (!is_array($decoded)) {
		jsonResponse(['error' => 'Не удалось разобрать JSON'], 400);
	}
	return $decoded;
}

function ensureStorage(string $file): void
{
	$dir = dirname($file);
	if (!is_dir($dir)) {
		mkdir($dir, 0777, true);
	}
	if (!file_exists($file)) {
		file_put_contents($file, json_encode(new stdClass(), JSON_PRETTY_PRINT));
	}
}

function createClient(array &$clients, array $payload): array
{
	$name = trim((string)($payload['name'] ?? ''));
	$email = trim((string)($payload['email'] ?? ''));
	if ($name === '' || $email === '') {
		jsonResponse(['error' => 'Имя и email обязательны'], 422);
	}
	$nextId = empty($clients)
		? 1
		: (max(array_map('intval', array_keys($clients))) + 1);
	$client = [
		'id' => $nextId,
		'name' => $name,
		'email' => $email
	];
	$clients[$nextId] = $client;
	return $client;
}

function updateClient(array $existing, array $payload): array
{
	$updated = $existing;
	if (array_key_exists('name', $payload)) {
		$name = trim((string)$payload['name']);
		if ($name === '') {
			jsonResponse(['error' => 'Имя не может быть пустым'], 422);
		}
		$updated['name'] = $name;
	}
	if (array_key_exists('email', $payload)) {
		$email = trim((string)$payload['email']);
		if ($email === '') {
			jsonResponse(['error' => 'Email не может быть пустым'], 422);
		}
		$updated['email'] = $email;
	}
	if ($updated === $existing) {
		jsonResponse(['error' => 'Нет данных для обновления'], 400);
	}
	return $updated;
}

function notFound(): void
{
	jsonResponse(['error' => 'Ресурс не найден'], 404);
}

function loadClients(string $file): array
{
	$contents = file_get_contents($file);
	if ($contents === false || trim($contents) === '') {
		return [];
	}
	$data = json_decode($contents, true);
	if (!is_array($data)) {
		return [];
	}
	$normalized = [];
	foreach ($data as $key => $client) {
		if (!is_array($client)) {
			continue;
		}
		$id = $client['id'] ?? (is_numeric($key) ? (int)$key : null);
		if ($id === null) {
			continue;
		}
		$id = (int)$id;
		$normalized[$id] = [
			'id' => $id,
			'name' => (string)($client['name'] ?? ''),
			'email' => (string)($client['email'] ?? ''),
		];
	}
	return $normalized;
}

function persistClients(string $file, array $clients): void
{
	ksort($clients);
	file_put_contents(
		$file,
		json_encode($clients, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
		LOCK_EX
	);
}