<?php
$dbPath = __DIR__ . '/plantita.db';
if (file_exists($dbPath)) {
    unlink($dbPath); // Borrar si existe
    echo "DB existente borrada.\n";
}

$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec('CREATE TABLE usuarios (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    contrasena_hash TEXT NOT NULL,
    creado_en TEXT DEFAULT CURRENT_TIMESTAMP
)');

$pdo->exec('CREATE TABLE plantas (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre TEXT NOT NULL,
    tipo TEXT NOT NULL,
    cuidado TEXT NOT NULL
)');

$pdo->exec('CREATE TABLE plantas_usuario (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    usuario_id INTEGER NOT NULL,
    planta_id INTEGER NOT NULL,
    agregado_en TEXT DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(usuario_id, planta_id),
    FOREIGN KEY(usuario_id) REFERENCES usuarios(id),
    FOREIGN KEY(planta_id) REFERENCES plantas(id)
)');

$pdo->exec('CREATE TABLE tareas (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    usuario_id INTEGER NOT NULL,
    titulo TEXT NOT NULL,
    descripcion TEXT NOT NULL,
    fecha_vencimiento TEXT NOT NULL,
    completed INTEGER DEFAULT 0,
    FOREIGN KEY(usuario_id) REFERENCES usuarios(id)
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

$stmt = $pdo->prepare('INSERT INTO plantas (nombre, tipo, cuidado) VALUES (?, ?, ?)');
foreach ($plants as $plant) {
    $stmt->execute([$plant['name'], $plant['type'], $plant['care']]);
}

// Usuario por defecto
echo "Insertando usuario...\n";
try {
    $stmt = $pdo->prepare('INSERT INTO usuarios (nombre, email, contrasena_hash) VALUES (?, ?, ?)');
    $result = $stmt->execute(['admin_flormeraki', 'admin@plantita.com', password_hash('admin', PASSWORD_DEFAULT)]);
    echo "Usuario admin insertado. Result: " . ($result ? 'OK' : 'Fail') . "\n";
} catch (Exception $e) {
    echo "Error insertando usuario: " . $e->getMessage() . "\n";
}

echo "Base de datos creada con éxito en $dbPath\n";
