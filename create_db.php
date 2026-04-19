<?php
$dbPath = __DIR__ . '/plantita.db';
if (file_exists($dbPath)) {
    unlink($dbPath); // Borrar si existe
    echo "DB existente borrada.\n";
}

$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec('CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
)');

$pdo->exec('CREATE TABLE plants (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    type TEXT NOT NULL,
    care TEXT NOT NULL
)');

$pdo->exec('CREATE TABLE user_plants (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    plant_id INTEGER NOT NULL,
    added_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(user_id) REFERENCES users(id),
    FOREIGN KEY(plant_id) REFERENCES plants(id)
)');

$pdo->exec('CREATE TABLE tasks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    title TEXT NOT NULL,
    description TEXT NOT NULL,
    due_date TEXT NOT NULL,
    completed INTEGER DEFAULT 0,
    FOREIGN KEY(user_id) REFERENCES users(id)
)');

$plants = [
    ['name' => 'Monstera', 'type' => 'interior', 'care' => 'Riego cada 7-10 días. Luz indirecta.'],
    ['name' => 'Cactus', 'type' => 'interior', 'care' => 'Riego cada 3 semanas. Luz directa.'],
    ['name' => 'Lavanda', 'type' => 'exterior', 'care' => 'Riego moderado. Suelo bien drenado.'],
    ['name' => 'Helecho', 'type' => 'interior', 'care' => 'Mantener humedad y luz indirecta.'],
    ['name' => 'Suculenta', 'type' => 'interior', 'care' => 'Riego ligero cada 2 semanas. Mucha luz.'],
    ['name' => 'Rosa', 'type' => 'exterior', 'care' => 'Riego diario. Sol pleno.'],
    ['name' => 'Orquídea', 'type' => 'interior', 'care' => 'Riego semanal. Luz indirecta.'],
    ['name' => 'Tulipán', 'type' => 'exterior', 'care' => 'Riego moderado. Sol parcial.'],
    ['name' => 'Bambú', 'type' => 'interior', 'care' => 'Riego frecuente. Luz baja.'],
    ['name' => 'Geranio', 'type' => 'exterior', 'care' => 'Riego cada 2 días. Sol pleno.'],
    ['name' => 'Ficus', 'type' => 'interior', 'care' => 'Riego semanal. Luz indirecta.'],
    ['name' => 'Hortensia', 'type' => 'exterior', 'care' => 'Riego abundante. Sombra parcial.'],
    ['name' => 'Pothos', 'type' => 'interior', 'care' => 'Riego cada 10 días. Luz baja.'],
    ['name' => 'Margarita', 'type' => 'exterior', 'care' => 'Riego moderado. Sol pleno.'],
    ['name' => 'Aloe Vera', 'type' => 'interior', 'care' => 'Riego cada 3 semanas. Luz directa.'],
    ['name' => 'Petunia', 'type' => 'exterior', 'care' => 'Riego diario. Sol pleno.'],
    ['name' => 'Begonia', 'type' => 'interior', 'care' => 'Riego semanal. Luz indirecta.'],
    ['name' => 'Clavel', 'type' => 'exterior', 'care' => 'Riego moderado. Sol parcial.'],
    ['name' => 'Yuca', 'type' => 'interior', 'care' => 'Riego cada 2 semanas. Luz directa.'],
    ['name' => 'Lirio', 'type' => 'exterior', 'care' => 'Riego abundante. Sombra.'],
];

$stmt = $pdo->prepare('INSERT INTO plants (name, type, care) VALUES (?, ?, ?)');
foreach ($plants as $plant) {
    $stmt->execute([$plant['name'], $plant['type'], $plant['care']]);
}

// Usuario por defecto
echo "Insertando usuario...\n";
try {
    $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)');
    $result = $stmt->execute(['admin_flormeraki', 'admin@plantita.com', password_hash('admin', PASSWORD_DEFAULT)]);
    echo "Usuario admin insertado. Result: " . ($result ? 'OK' : 'Fail') . "\n";
} catch (Exception $e) {
    echo "Error insertando usuario: " . $e->getMessage() . "\n";
}

echo "Base de datos creada con éxito en $dbPath\n";
