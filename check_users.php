<?php
$dbPath = __DIR__ . '/plantita.db';
$pdo = new PDO('sqlite:' . $dbPath);
$users = $pdo->query('SELECT id, nombre AS name, email FROM usuarios')->fetchAll(PDO::FETCH_ASSOC);
echo 'Usuarios registrados: ' . count($users) . PHP_EOL;
foreach ($users as $u) {
    echo $u['id'] . ': ' . $u['name'] . ' - ' . $u['email'] . PHP_EOL;
}
