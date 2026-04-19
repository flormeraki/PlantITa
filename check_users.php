<?php
$dbPath = __DIR__ . '/plantita.db';
$pdo = new PDO('sqlite:' . $dbPath);
$users = $pdo->query('SELECT id, name, email FROM users')->fetchAll(PDO::FETCH_ASSOC);
echo 'Usuarios registrados: ' . count($users) . PHP_EOL;
foreach ($users as $u) {
    echo $u['id'] . ': ' . $u['name'] . ' - ' . $u['email'] . PHP_EOL;
}
