<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? '';
$dbPath = __DIR__ . '/plantita.db';
$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_user_plants_user_plant ON user_plants(user_id, plant_id)');

function jsonResponse($data) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

if ($action === 'check_session') {
    if (!empty($_SESSION['user'])) {
        jsonResponse(['success' => true, 'user' => $_SESSION['user']]);
    }
    jsonResponse(['success' => true, 'user' => null]);
}

if ($action === 'logout') {
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();
    jsonResponse(['success' => true]);
}

if ($action === 'register') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($name) || empty($email) || empty($password)) {
        jsonResponse(['success' => false, 'message' => 'Todos los campos son obligatorios.']);
    }

    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        jsonResponse(['success' => false, 'message' => 'El correo ya esta registrado.']);
    }

    $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)');
    $stmt->execute([$name, $email, hashPassword($password)]);
    $userId = $pdo->lastInsertId();
    $_SESSION['user'] = ['id' => $userId, 'name' => $name, 'email' => $email];
    jsonResponse(['success' => true, 'user' => $_SESSION['user']]);
}

if ($action === 'login') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        jsonResponse(['success' => false, 'message' => 'Correo y contrasena son obligatorios.']);
    }

    $stmt = $pdo->prepare('SELECT id, name, email, password_hash FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user || !verifyPassword($password, $user['password_hash'])) {
        jsonResponse(['success' => false, 'message' => 'Credenciales invalidas.']);
    }

    unset($user['password_hash']);
    $_SESSION['user'] = $user;
    jsonResponse(['success' => true, 'user' => $user]);
}

if (empty($_SESSION['user'])) {
    jsonResponse(['success' => false, 'message' => 'Por favor inicia sesion.']);
}

if ($action === 'get_plants') {
    $stmt = $pdo->query('SELECT id, name, type, care FROM plants ORDER BY name');
    $plants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    jsonResponse(['success' => true, 'plants' => $plants]);
}

if ($action === 'add_my_plant') {
    $userId = (int) $_SESSION['user']['id'];
    $plantId = (int) ($_POST['plant_id'] ?? 0);

    if (!$plantId) {
        jsonResponse(['success' => false, 'message' => 'ID de planta invalido.']);
    }

    $stmt = $pdo->prepare('SELECT id FROM plants WHERE id = ?');
    $stmt->execute([$plantId]);
    if (!$stmt->fetch()) {
        jsonResponse(['success' => false, 'message' => 'La planta seleccionada no existe.']);
    }

    $stmt = $pdo->prepare('SELECT id FROM user_plants WHERE user_id = ? AND plant_id = ?');
    $stmt->execute([$userId, $plantId]);
    if ($stmt->fetch()) {
        jsonResponse(['success' => false, 'message' => 'Ya tienes esta planta en tu coleccion.']);
    }

    $stmt = $pdo->prepare('INSERT INTO user_plants (user_id, plant_id, added_at) VALUES (?, ?, datetime("now"))');
    $stmt->execute([$userId, $plantId]);
    jsonResponse(['success' => true, 'message' => 'Planta agregada correctamente.']);
}

if ($action === 'remove_my_plant') {
    $userId = (int) $_SESSION['user']['id'];
    $plantId = (int) ($_POST['plant_id'] ?? 0);

    if (!$plantId) {
        jsonResponse(['success' => false, 'message' => 'ID de planta invalido.']);
    }

    $stmt = $pdo->prepare('DELETE FROM user_plants WHERE user_id = ? AND plant_id = ?');
    $stmt->execute([$userId, $plantId]);

    if ($stmt->rowCount() === 0) {
        jsonResponse(['success' => false, 'message' => 'Esa planta no estaba en tu coleccion.']);
    }

    jsonResponse(['success' => true, 'message' => 'Planta eliminada de tu coleccion.']);
}

if ($action === 'get_my_plants') {
    $userId = (int) $_SESSION['user']['id'];
    $stmt = $pdo->prepare('
        SELECT
            p.id,
            p.name,
            p.type,
            p.care,
            "Activa" AS status
        FROM plants p
        JOIN user_plants up ON p.id = up.plant_id
        WHERE up.user_id = ?
        ORDER BY p.name
    ');
    $stmt->execute([$userId]);
    $plants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    jsonResponse(['success' => true, 'plants' => $plants]);
}

if ($action === 'get_tasks') {
    $userId = $_SESSION['user']['id'];
    $stmt = $pdo->prepare('SELECT id, title, description, due_date FROM tasks WHERE user_id = ? ORDER BY due_date ASC');
    $stmt->execute([$userId]);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    jsonResponse(['success' => true, 'tasks' => $tasks]);
}

jsonResponse(['success' => false, 'message' => 'Accion no reconocida.']);
