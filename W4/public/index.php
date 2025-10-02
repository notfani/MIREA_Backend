<?php
	require_once __DIR__ . '/../vendor/autoload.php';
	
	use GraphQL\GraphQL;
	use GraphQL\Type\Definition\ObjectType;
	use GraphQL\Type\Definition\Type;
	use GraphQL\Type\Schema;

// Временное хранилище
	$clients = [];
	$nextId = 1;

// Тип Client
	$clientType = new ObjectType([
		'name' => 'Client',
		'fields' => [
			'id' => Type::nonNull(Type::int()),
			'name' => Type::nonNull(Type::string()),
			'email' => Type::nonNull(Type::string()),
		]
	]);

// Схема
	$schema = new Schema([
		'query' => new ObjectType([
			'name' => 'Query',
			'fields' => [
				'clients' => [
					'type' => Type::listOf($clientType),
					'resolve' => fn() => array_values($clients)
				],
				'client' => [
					'type' => $clientType,
					'args' => ['id' => Type::nonNull(Type::int())],
					'resolve' => fn($root, $args) => $clients[$args['id']] ?? null
				]
			]
		]),
		'mutation' => new ObjectType([
			'name' => 'Mutation',
			'fields' => [
				'createClient' => [
					'type' => $clientType,
					'args' => [
						'name' => Type::nonNull(Type::string()),
						'email' => Type::nonNull(Type::string())
					],
					'resolve' => function ($root, $args) use (&$clients, &$nextId) {
						$client = [
							'id' => $nextId,
							'name' => $args['name'],
							'email' => $args['email']
						];
						$clients[$nextId] = $client;
						$nextId++;
						return $client;
					}
				],
				'updateClient' => [
					'type' => $clientType,
					'args' => [
						'id' => Type::nonNull(Type::int()),
						'name' => Type::string(),
						'email' => Type::string()
					],
					'resolve' => function ($root, $args) use (&$clients) {
						if (!isset($clients[$args['id']])) return null;
						if (isset($args['name'])) $clients[$args['id']]['name'] = $args['name'];
						if (isset($args['email'])) $clients[$args['id']]['email'] = $args['email'];
						return $clients[$args['id']];
					}
				],
				'deleteClient' => [
					'type' => Type::boolean(),
					'args' => ['id' => Type::nonNull(Type::int())],
					'resolve' => function ($root, $args) use (&$clients) {
						if (!isset($clients[$args['id']])) return false;
						unset($clients[$args['id']]);
						return true;
					}
				]
			]
		])
	]);

// Обработка запроса
	$input = json_decode(file_get_contents('php://input'), true);
	$query = $input['query'];
	$variables = $input['variables'] ?? null;
	
	try {
		$result = GraphQL::executeQuery($schema, $query, null, null, $variables);
		$output = $result->toArray();
	} catch (\Throwable $e) {
		$output = ['error' => ['message' => $e->getMessage()]];
	}
	
	header('Content-Type: application/json');
	echo json_encode($output);