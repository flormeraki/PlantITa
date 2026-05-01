<?php
header('Content-Type: application/json; charset=utf-8');

$dbPath = __DIR__ . '/plantita.db';
$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $pdo->query('SELECT id, nombre AS name, tipo AS type, cuidado AS care FROM plantas ORDER BY nombre');
$plants = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['success' => true, 'plants' => $plants]);
