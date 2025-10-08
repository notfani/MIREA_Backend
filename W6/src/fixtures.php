<?php
require_once __DIR__ . '/_bootstrap.php';

$faker = Faker\Factory::create();

$stmt = $pdo->prepare("INSERT INTO sales (product, qty, price, region, sold_at)
                        VALUES (?, ?, ?, ?, ?)");

for ($i = 0; $i < 60; $i++) {
    $stmt->execute([
        $faker->catchPhrase(),
        $faker->numberBetween(1, 100),
        $faker->randomFloat(2, 5, 500),
        $faker->randomElement(['EU', 'US', 'ASIA']),
        $faker->dateTimeBetween('-1 year')->format('Y-m-d'),
    ]);
}
echo "60 фикстур вставлено.\n";