<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode(['test' => 'PHP OK']);
    exit;
}

$dbPath = __DIR__ . '/plantita.db';
$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('PRAGMA foreign_keys = ON');

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = trim($_POST['password'] ?? '');

if (empty($name) || empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Todos los campos son obligatorios.']);
    exit;
}

$stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = ?');
$stmt->execute([$email]);
if ($stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'El correo ya está registrado.']);
    exit;
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $pdo->prepare('INSERT INTO usuarios (nombre, email, contrasena_hash) VALUES (?, ?, ?)');
$stmt->execute([$name, $email, $passwordHash]);
$userId = $pdo->lastInsertId();

$_SESSION['user'] = ['id' => $userId, 'name' => $name, 'email' => $email];
echo json_encode(['success' => true, 'user' => $_SESSION['user']]);
