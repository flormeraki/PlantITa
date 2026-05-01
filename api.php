<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? '';
$dbPath = __DIR__ . '/plantita.db';
$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('PRAGMA foreign_keys = ON');
$pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_plantas_usuario_usuario_planta ON plantas_usuario(usuario_id, planta_id)');

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

function normalizeDate($date) {
    $date = trim((string) $date);
    $parsed = DateTime::createFromFormat('Y-m-d', $date);

    if (!$parsed || $parsed->format('Y-m-d') !== $date) {
        return (new DateTime('today'))->format('Y-m-d');
    }

    return $date;
}

function daysBetween($from, $to) {
    $start = new DateTime(substr($from, 0, 10));
    $end = new DateTime(substr($to, 0, 10));
    return (int) $start->diff($end)->format('%r%a');
}

function careFrequencyFromText($care) {
    $care = function_exists('mb_strtolower') ? mb_strtolower($care, 'UTF-8') : strtolower($care);

    if (strpos($care, 'diario') !== false || strpos($care, 'diaria') !== false) {
        return 1;
    }
    if (strpos($care, 'cada 2') !== false || strpos($care, 'frecuente') !== false || strpos($care, 'abundante') !== false) {
        return 2;
    }
    if (strpos($care, 'moderado') !== false || strpos($care, 'moderada') !== false) {
        return 3;
    }
    if (strpos($care, 'semanal') !== false || strpos($care, '7-10') !== false) {
        return 7;
    }
    if (strpos($care, '10') !== false) {
        return 10;
    }
    if (strpos($care, '2 semana') !== false) {
        return 14;
    }
    if (strpos($care, '3 semana') !== false) {
        return 21;
    }

    return 7;
}

function careRulesForPlant($plant) {
    $wateringFrequency = careFrequencyFromText($plant['care'] ?? '');
    $type = $plant['type'] ?? 'interior';

    return [
        [
            'type' => 'Riego',
            'frequency_days' => $wateringFrequency,
            'description' => $plant['care'] ?? 'Revisar humedad del sustrato antes de regar.'
        ],
        [
            'type' => 'Fertilizacion',
            'frequency_days' => $type === 'exterior' ? 30 : 45,
            'description' => 'Aplicar fertilizante suave segun la necesidad de la planta.'
        ],
        [
            'type' => 'Poda',
            'frequency_days' => $type === 'exterior' ? 21 : 35,
            'description' => 'Retirar hojas secas y revisar crecimiento.'
        ]
    ];
}

function isCareDueOnDate($addedAt, $frequencyDays, $date) {
    $days = daysBetween($addedAt, $date);
    return $days >= 0 && $days % $frequencyDays === 0;
}

function buildCareTasks($plants, $date) {
    $today = (new DateTime('today'))->format('Y-m-d');
    $tasks = [];

    foreach ($plants as $plant) {
        foreach (careRulesForPlant($plant) as $rule) {
            if (!isCareDueOnDate($plant['added_at'], $rule['frequency_days'], $date)) {
                continue;
            }

            $tasks[] = [
                'id' => $plant['id'] . '-' . strtolower($rule['type']) . '-' . $date,
                'type' => $rule['type'],
                'plant_id' => (int) $plant['id'],
                'plant_name' => $plant['name'],
                'plant_type' => $plant['type'],
                'date' => $date,
                'frequency_days' => $rule['frequency_days'],
                'description' => $rule['description'],
                'is_today' => $date === $today
            ];
        }
    }

    usort($tasks, function ($a, $b) {
        return [$a['plant_name'], $a['type']] <=> [$b['plant_name'], $b['type']];
    });

    return $tasks;
}

function nextCareTasksForPlant($plant, $daysAhead = 14) {
    $today = (new DateTime('today'))->format('Y-m-d');
    $tasks = [];

    for ($offset = 0; $offset <= $daysAhead; $offset++) {
        $date = (new DateTime('today'))->modify('+' . $offset . ' days')->format('Y-m-d');
        foreach (buildCareTasks([$plant], $date) as $task) {
            $tasks[] = $task;
        }
    }

    usort($tasks, function ($a, $b) {
        return [$a['date'], $a['type']] <=> [$b['date'], $b['type']];
    });

    return array_slice($tasks, 0, 6);
}

function getUserPlants($pdo, $userId) {
    $stmt = $pdo->prepare('
        SELECT
            p.id,
            p.nombre AS name,
            p.tipo AS type,
            p.cuidado AS care,
            pu.agregado_en AS added_at
        FROM plantas p
        JOIN plantas_usuario pu ON p.id = pu.planta_id
        WHERE pu.usuario_id = ?
        ORDER BY p.nombre
    ');
    $stmt->execute([$userId]);
    $plants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $today = (new DateTime('today'))->format('Y-m-d');

    foreach ($plants as &$plant) {
        $todayTasks = buildCareTasks([$plant], $today);
        $plant['status'] = count($todayTasks) > 0 ? 'Necesita cuidado' : 'OK';
        $plant['status_detail'] = count($todayTasks) > 0
            ? implode(', ', array_column($todayTasks, 'type'))
            : 'Sin tareas pendientes hoy';
        $plant['next_cares'] = nextCareTasksForPlant($plant);
    }

    return $plants;
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

    $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        jsonResponse(['success' => false, 'message' => 'El correo ya esta registrado.']);
    }

    $stmt = $pdo->prepare('INSERT INTO usuarios (nombre, email, contrasena_hash) VALUES (?, ?, ?)');
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

    $stmt = $pdo->prepare('SELECT id, nombre AS name, email, contrasena_hash FROM usuarios WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user || !verifyPassword($password, $user['contrasena_hash'])) {
        jsonResponse(['success' => false, 'message' => 'Credenciales invalidas.']);
    }

    unset($user['contrasena_hash']);
    $_SESSION['user'] = $user;
    jsonResponse(['success' => true, 'user' => $user]);
}

if (empty($_SESSION['user'])) {
    jsonResponse(['success' => false, 'message' => 'Por favor inicia sesion.']);
}

if ($action === 'get_plants') {
    $stmt = $pdo->query('SELECT id, nombre AS name, tipo AS type, cuidado AS care FROM plantas ORDER BY nombre');
    $plants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    jsonResponse(['success' => true, 'plants' => $plants]);
}

if ($action === 'add_my_plant') {
    $userId = (int) $_SESSION['user']['id'];
    $plantId = (int) ($_POST['plant_id'] ?? 0);

    if (!$plantId) {
        jsonResponse(['success' => false, 'message' => 'ID de planta invalido.']);
    }

    $stmt = $pdo->prepare('SELECT id FROM plantas WHERE id = ?');
    $stmt->execute([$plantId]);
    if (!$stmt->fetch()) {
        jsonResponse(['success' => false, 'message' => 'La planta seleccionada no existe.']);
    }

    $stmt = $pdo->prepare('SELECT id FROM plantas_usuario WHERE usuario_id = ? AND planta_id = ?');
    $stmt->execute([$userId, $plantId]);
    if ($stmt->fetch()) {
        jsonResponse(['success' => false, 'message' => 'Ya tienes esta planta en tu coleccion.']);
    }

    $stmt = $pdo->prepare('INSERT INTO plantas_usuario (usuario_id, planta_id, agregado_en) VALUES (?, ?, datetime("now"))');
    $stmt->execute([$userId, $plantId]);
    jsonResponse(['success' => true, 'message' => 'Planta agregada correctamente.']);
}

if ($action === 'remove_my_plant') {
    $userId = (int) $_SESSION['user']['id'];
    $plantId = (int) ($_POST['plant_id'] ?? 0);

    if (!$plantId) {
        jsonResponse(['success' => false, 'message' => 'ID de planta invalido.']);
    }

    $stmt = $pdo->prepare('DELETE FROM plantas_usuario WHERE usuario_id = ? AND planta_id = ?');
    $stmt->execute([$userId, $plantId]);

    if ($stmt->rowCount() === 0) {
        jsonResponse(['success' => false, 'message' => 'Esa planta no estaba en tu coleccion.']);
    }

    jsonResponse(['success' => true, 'message' => 'Planta eliminada de tu coleccion.']);
}

if ($action === 'get_my_plants') {
    $userId = (int) $_SESSION['user']['id'];
    $plants = getUserPlants($pdo, $userId);
    jsonResponse(['success' => true, 'plants' => $plants]);
}

if ($action === 'get_plant_cares') {
    $userId = (int) $_SESSION['user']['id'];
    $plantId = (int) ($_POST['plant_id'] ?? 0);

    if (!$plantId) {
        jsonResponse(['success' => false, 'message' => 'ID de planta invalido.']);
    }

    $plants = array_filter(getUserPlants($pdo, $userId), function ($plant) use ($plantId) {
        return (int) $plant['id'] === $plantId;
    });

    if (!$plants) {
        jsonResponse(['success' => false, 'message' => 'La planta no pertenece a tu coleccion.']);
    }

    $plant = array_values($plants)[0];
    jsonResponse([
        'success' => true,
        'plant' => $plant,
        'cares' => nextCareTasksForPlant($plant, 21)
    ]);
}

if ($action === 'get_care_schedule') {
    $userId = (int) $_SESSION['user']['id'];
    $date = normalizeDate($_POST['date'] ?? '');
    $plants = getUserPlants($pdo, $userId);
    $tasks = buildCareTasks($plants, $date);

    jsonResponse([
        'success' => true,
        'date' => $date,
        'tasks' => $tasks
    ]);
}

if ($action === 'get_tasks') {
    $userId = $_SESSION['user']['id'];
    $stmt = $pdo->prepare('SELECT id, titulo AS title, descripcion AS description, fecha_vencimiento AS due_date FROM tareas WHERE usuario_id = ? ORDER BY fecha_vencimiento ASC');
    $stmt->execute([$userId]);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    jsonResponse(['success' => true, 'tasks' => $tasks]);
}

jsonResponse(['success' => false, 'message' => 'Accion no reconocida.']);
