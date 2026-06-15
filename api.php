<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? '';
$dbPath = __DIR__ . '/plantita.db';
$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('PRAGMA foreign_keys = ON');
$pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_plantas_usuario_usuario_planta ON plantas_usuario(usuario_id, planta_id)');
$pdo->exec('CREATE TABLE IF NOT EXISTS plant_care_history (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    usuario_id INTEGER NOT NULL,
    planta_id INTEGER NOT NULL,
    tipo TEXT NOT NULL,
    evento TEXT NOT NULL,
    detalles TEXT DEFAULT "",
    fecha TEXT NOT NULL,
    FOREIGN KEY(usuario_id) REFERENCES usuarios(id),
    FOREIGN KEY(planta_id) REFERENCES plantas(id)
)');

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

function addPlantCareHistory($pdo, $userId, $plantId, $type, $event, $details = '') {
    $stmt = $pdo->prepare('INSERT INTO plant_care_history (usuario_id, planta_id, tipo, evento, detalles, fecha) VALUES (?, ?, ?, ?, ?, datetime("now"))');
    $stmt->execute([$userId, $plantId, $type, $event, $details]);
    return $pdo->lastInsertId();
}

function getPlantCareHistory($pdo, $userId, $plantId, $limit = 10) {
    $stmt = $pdo->prepare('SELECT id, tipo, evento, detalles, fecha FROM plant_care_history WHERE usuario_id = ? AND planta_id = ? ORDER BY fecha DESC, id DESC LIMIT ?');
    $stmt->bindValue(1, $userId, PDO::PARAM_INT);
    $stmt->bindValue(2, $plantId, PDO::PARAM_INT);
    $stmt->bindValue(3, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
        ],
        [
            'type' => 'Fumigar',
            'frequency_days' => 28,
            'description' => 'Proteger contra plagas y hongos con un tratamiento suave.'
        ],
        [
            'type' => 'Transplantar',
            'frequency_days' => 90,
            'description' => 'Renovar sustrato y dar espacio para el crecimiento de la raiz.'
        ]
    ];
}

function hasKeywords($text, $keywords) {
    $text = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);

    foreach ($keywords as $keyword) {
        if (strpos($text, function_exists('mb_strtolower') ? mb_strtolower($keyword, 'UTF-8') : strtolower($keyword)) !== false) {
            return true;
        }
    }

    return false;
}

function getPlantDifficulty($plant) {
    $care = $plant['care'] ?? '';
    $plantText = ($plant['name'] ?? '') . ' ' . $care;
    $wateringFrequency = careFrequencyFromText($care);
    $score = 0;
    $reasons = [];

    if ($wateringFrequency <= 2) {
        $score += 2;
        $reasons[] = 'necesita riego muy frecuente';
    } elseif ($wateringFrequency <= 3) {
        $score += 1;
        $reasons[] = 'requiere controlar seguido la humedad';
    } elseif ($wateringFrequency >= 14) {
        $score -= 1;
        $reasons[] = 'tolera riegos espaciados';
    }

    if (hasKeywords($plantText, ['mantener humedad', 'humedad', 'abundante'])) {
        $score += 1;
        $reasons[] = 'es sensible a la humedad ambiental y del sustrato';
    }

    if (hasKeywords($plantText, ['orquidea', 'orquídea', 'helecho', 'hortensia', 'begonia'])) {
        $score += 1;
        $reasons[] = 'puede reaccionar a cambios de luz o ambiente';
    }

    if (hasKeywords($plantText, ['cactus', 'aloe', 'suculenta', 'yuca', 'pothos'])) {
        $score -= 1;
        $reasons[] = 'es resistente y perdona pequeños descuidos';
    }

    if ($score <= 0) {
        return [
            'level' => 'facil',
            'label' => 'Facil',
            'score' => 1,
            'experience' => 'Ideal para principiantes',
            'description' => 'Es una planta resistente, con cuidados simples y buen margen ante pequeños descuidos.',
            'reasons' => array_slice(array_values(array_unique($reasons)), 0, 2)
        ];
    }

    if ($score <= 2) {
        return [
            'level' => 'intermedia',
            'label' => 'Intermedia',
            'score' => 2,
            'experience' => 'Recomendada con algo de experiencia',
            'description' => 'Necesita cierta constancia para equilibrar riego, luz y condiciones ambientales.',
            'reasons' => array_slice(array_values(array_unique($reasons)), 0, 2)
        ];
    }

    return [
        'level' => 'avanzada',
        'label' => 'Avanzada',
        'score' => 3,
        'experience' => 'Para personas con experiencia',
        'description' => 'Requiere seguimiento frecuente y responde con mayor sensibilidad a cambios de cuidado.',
        'reasons' => array_slice(array_values(array_unique($reasons)), 0, 2)
    ];
}

function getSouthernSeason($date = null) {
    $date = $date ?: new DateTime('today');
    $month = (int) $date->format('n');

    if (in_array($month, [12, 1, 2], true)) {
        return 'verano';
    }
    if (in_array($month, [3, 4, 5], true)) {
        return 'otono';
    }
    if (in_array($month, [6, 7, 8], true)) {
        return 'invierno';
    }

    return 'primavera';
}

function seasonalWateringText($baseFrequency, $factor, $isSucculent) {
    $days = max(1, (int) round($baseFrequency * $factor));

    if ($isSucculent) {
        return 'Revisa que el sustrato este completamente seco. Como referencia, riega cada ' . $days . ' dias.';
    }

    return 'Comprueba la humedad antes de regar. Como referencia, riega cada ' . $days . ' dias.';
}

function getSeasonalCare($plant) {
    $currentSeason = getSouthernSeason();
    $baseFrequency = careFrequencyFromText($plant['care'] ?? '');
    $plantText = ($plant['name'] ?? '') . ' ' . ($plant['care'] ?? '');
    $isSucculent = hasKeywords($plantText, ['cactus', 'aloe', 'suculenta', 'suculentas', 'agave', 'sedum']);
    $needsSoftLight = hasKeywords($plantText, ['luz indirecta', 'luz baja', 'sombra', 'sombra parcial']);
    $type = $plant['type'] ?? 'interior';

    $lightSummer = $needsSoftLight
        ? 'Protegela del sol fuerte del mediodia y mantenla con luz filtrada.'
        : 'Dale buena luz y vigila quemaduras durante las horas de mayor intensidad.';
    $lightWinter = $type === 'interior'
        ? 'Acercala a una ventana luminosa y girala periodicamente para que crezca pareja.'
        : 'Busca el sector con mas horas de luz y protegela de heladas o viento frio.';

    $seasons = [
        'verano' => [
            'name' => 'Verano',
            'summary' => 'Mayor calor y evaporacion: controla la humedad con mas frecuencia.',
            'cares' => [
                ['category' => 'Riego', 'description' => seasonalWateringText($baseFrequency, 0.7, $isSucculent)],
                ['category' => 'Luz', 'description' => $lightSummer],
                ['category' => 'Fertilizacion', 'description' => 'Fertiliza en dosis suave cada 3 o 4 semanas si la planta esta creciendo.'],
                ['category' => 'Ambiente', 'description' => $type === 'interior'
                    ? 'Alejala del aire acondicionado y mejora la humedad ambiental sin encharcar.'
                    : 'Riega temprano o al atardecer y revisa plagas favorecidas por el calor.']
            ]
        ],
        'otono' => [
            'name' => 'Otono',
            'summary' => 'El crecimiento comienza a bajar: reduce gradualmente agua y abono.',
            'cares' => [
                ['category' => 'Riego', 'description' => seasonalWateringText($baseFrequency, 1.1, $isSucculent)],
                ['category' => 'Luz', 'description' => 'Aprovecha la luz disponible y evita cambios bruscos de ubicacion.'],
                ['category' => 'Fertilizacion', 'description' => 'Reduce el fertilizante y suspendelo si deja de producir hojas o brotes.'],
                ['category' => 'Ambiente', 'description' => 'Retira hojas secas, revisa hongos y preparala para temperaturas mas bajas.']
            ]
        ],
        'invierno' => [
            'name' => 'Invierno',
            'summary' => 'Menor actividad: prioriza luz, abrigo y riegos espaciados.',
            'cares' => [
                ['category' => 'Riego', 'description' => seasonalWateringText($baseFrequency, $isSucculent ? 1.8 : 1.5, $isSucculent)],
                ['category' => 'Luz', 'description' => $lightWinter],
                ['category' => 'Fertilizacion', 'description' => 'Pausa el fertilizante mientras el crecimiento este detenido.'],
                ['category' => 'Ambiente', 'description' => $type === 'interior'
                    ? 'Evita corrientes frias y el contacto directo con calefactores.'
                    : 'Protege raices y follaje de heladas; evita podas intensas en dias muy frios.']
            ]
        ],
        'primavera' => [
            'name' => 'Primavera',
            'summary' => 'Etapa de crecimiento: aumenta el seguimiento y reactiva la nutricion.',
            'cares' => [
                ['category' => 'Riego', 'description' => seasonalWateringText($baseFrequency, 0.85, $isSucculent)],
                ['category' => 'Luz', 'description' => 'Adaptala de forma gradual a mas horas de luz para evitar quemaduras.'],
                ['category' => 'Fertilizacion', 'description' => 'Reinicia el fertilizante en dosis suave cada 3 o 4 semanas.'],
                ['category' => 'Ambiente', 'description' => 'Es buen momento para podar, renovar sustrato y controlar brotes de plagas.']
            ]
        ]
    ];

    foreach ($seasons as $key => &$season) {
        $season['key'] = $key;
        $season['is_current'] = $key === $currentSeason;
    }
    unset($season);

    return [
        'hemisphere' => 'sur',
        'current_season' => $currentSeason,
        'seasons' => $seasons
    ];
}

function buildRecommendation($message, $severity = 'suggestion') {
    $severity = in_array($severity, ['urgent', 'warning', 'suggestion'], true) ? $severity : 'suggestion';
    $severityText = [
        'urgent' => 'Urgente',
        'warning' => 'Advertencia',
        'suggestion' => 'Sugerencia'
    ][$severity];

    return [
        'message' => $message,
        'severity' => $severity,
        'severity_text' => $severityText
    ];
}

function getPlantRecommendations($plant) {
    $recommendations = [];
    $today = (new DateTime('today'))->format('Y-m-d');
    $wateringFrequency = careFrequencyFromText($plant['care'] ?? '');
    $daysSinceAdded = daysBetween($plant['added_at'] ?? $today, $today);
    $isSucculent = hasKeywords($plant['name'] . ' ' . $plant['care'], ['cactus', 'aloe', 'suculenta', 'suculentas', 'agave', 'sedum']);
    $lowLightCare = hasKeywords($plant['care'], ['luz indirecta', 'luz baja', 'sombra', 'sombra parcial']);
    $directLightCare = hasKeywords($plant['care'], ['sol pleno', 'sol parcial', 'luz directa', 'directa']);
    $hasDrainageAdvice = hasKeywords($plant['care'], ['drenado', 'suelo bien drenado', 'bien drenado']);
    $type = $plant['type'] ?? 'interior';
    $name = $plant['name'] ?? 'Esta planta';

    if ($wateringFrequency > 0) {
        if ($daysSinceAdded >= $wateringFrequency) {
            $cycles = (int) floor($daysSinceAdded / $wateringFrequency);
            $lastExpected = $cycles * $wateringFrequency;
            $overdueDays = $daysSinceAdded - $lastExpected;

            if ($overdueDays > 0) {
                $severity = $overdueDays >= 3 ? 'urgent' : 'warning';
                $recommendations[] = buildRecommendation(
                    'Hace ' . $overdueDays . ' día' . ($overdueDays === 1 ? '' : 's') . ' que no registrás riego.',
                    $severity
                );

                if ($isSucculent) {
                    $recommendations[] = buildRecommendation(
                        'Tu ' . $name . ' es una planta de riego esporádico; deja secar bien el sustrato antes de volver a regar.',
                        'warning'
                    );
                }
            } elseif ($overdueDays === 0) {
                $recommendations[] = buildRecommendation('Probablemente toca regar hoy.', 'warning');
            }
        } else {
            $daysUntilNext = $wateringFrequency - $daysSinceAdded;

            if ($daysUntilNext <= 2) {
                $recommendations[] = buildRecommendation(
                    'Faltan ' . $daysUntilNext . ' día' . ($daysUntilNext === 1 ? '' : 's') . ' para el próximo riego.',
                    'suggestion'
                );
            }

            if ($isSucculent && $daysSinceAdded > 0) {
                $recommendations[] = buildRecommendation(
                    'Revisa la humedad antes de regar: las suculentas necesitan menos agua y descansos largos entre riegos.',
                    'suggestion'
                );
            }
        }
    }

    if ($isSucculent && $wateringFrequency > 0 && $wateringFrequency <= 14 && $daysSinceAdded > 0) {
        $recommendations[] = buildRecommendation(
            'Para suculentas como esta, menos riego suele ser mejor que demasiada frecuencia.',
            'warning'
        );
    }

    if ($type === 'interior' && $lowLightCare) {
        $recommendations[] = buildRecommendation(
            'Esta planta interior necesita buena luz indirecta; ubicala cerca de una ventana sin sol directo fuerte.',
            'warning'
        );
    } elseif ($type === 'exterior' && $directLightCare) {
        $recommendations[] = buildRecommendation(
            'Asegurate de que esta planta exterior reciba suficiente sol y tierra bien drenada.',
            'suggestion'
        );
    }

    if ($hasDrainageAdvice && $type === 'exterior') {
        $recommendations[] = buildRecommendation(
            'Mantén el suelo bien drenado para evitar encharcamientos después del riego.',
            'suggestion'
        );
    }

    usort($recommendations, function ($a, $b) {
        $priority = ['urgent' => 1, 'warning' => 2, 'suggestion' => 3];
        return $priority[$a['severity']] <=> $priority[$b['severity']];
    });

    $unique = [];
    foreach ($recommendations as $recommendation) {
        $key = $recommendation['message'] . '|' . $recommendation['severity'];
        if (!isset($unique[$key])) {
            $unique[$key] = $recommendation;
        }
    }

    return array_slice(array_values($unique), 0, 4);
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
        $plant['recommendations'] = getPlantRecommendations($plant);
        $plant['seasonal_care'] = getSeasonalCare($plant);
        $plant['difficulty'] = getPlantDifficulty($plant);
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
    foreach ($plants as &$plant) {
        $plant['difficulty'] = getPlantDifficulty($plant);
    }
    unset($plant);
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

    $stmt = $pdo->prepare('SELECT id, nombre AS name, tipo AS type, cuidado AS care FROM plantas WHERE id = ?');
    $stmt->execute([$plantId]);
    $plant = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$plant) {
        jsonResponse(['success' => false, 'message' => 'La planta no existe.']);
    }

    $stmt = $pdo->prepare('SELECT agregado_en FROM plantas_usuario WHERE usuario_id = ? AND planta_id = ?');
    $stmt->execute([$userId, $plantId]);
    $myPlant = $stmt->fetch(PDO::FETCH_ASSOC);

    $plant['added_at'] = $myPlant['agregado_en'] ?? (new DateTime('today'))->format('Y-m-d');
    $plant['status'] = $myPlant ? 'En coleccion' : 'Vista previa';
    $plant['status_detail'] = $myPlant ? 'Revisar cuidados proximos' : 'Vista previa de cuidados disponibles';
    $plant['recommendations'] = getPlantRecommendations($plant);
    $plant['history'] = getPlantCareHistory($pdo, $userId, $plantId, 10);
    $plant['seasonal_care'] = getSeasonalCare($plant);
    $plant['difficulty'] = getPlantDifficulty($plant);

    jsonResponse([
        'success' => true,
        'plant' => $plant,
        'cares' => nextCareTasksForPlant($plant, 21)
    ]);
}

if ($action === 'log_plant_event') {
    $userId = (int) $_SESSION['user']['id'];
    $plantId = (int) ($_POST['plant_id'] ?? 0);
    $type = trim($_POST['type'] ?? '');
    $event = trim($_POST['event'] ?? '');
    $details = trim($_POST['details'] ?? '');

    if (!$plantId || !$type || !$event) {
        jsonResponse(['success' => false, 'message' => 'Datos de evento incompletos.']);
    }

    $stmt = $pdo->prepare('SELECT id FROM plantas WHERE id = ?');
    $stmt->execute([$plantId]);
    if (!$stmt->fetch()) {
        jsonResponse(['success' => false, 'message' => 'La planta no existe.']);
    }

    addPlantCareHistory($pdo, $userId, $plantId, $type, $event, $details);
    jsonResponse(['success' => true, 'message' => 'Evento registrado correctamente.']);
}

if ($action === 'get_recommendations') {
    $userId = (int) $_SESSION['user']['id'];
    $plants = getUserPlants($pdo, $userId);
    $recommendations = [];

    foreach ($plants as $plant) {
        foreach ($plant['recommendations'] as $recommendation) {
            $recommendations[] = [
                'plant_id' => (int) $plant['id'],
                'plant_name' => $plant['name'],
                'message' => $recommendation['message'],
                'severity' => $recommendation['severity'],
                'severity_text' => $recommendation['severity_text'],
            ];
        }
    }

    usort($recommendations, function ($a, $b) {
        $priority = ['urgent' => 1, 'warning' => 2, 'suggestion' => 3];
        if ($priority[$a['severity']] !== $priority[$b['severity']]) {
            return $priority[$a['severity']] <=> $priority[$b['severity']];
        }
        return strcmp($a['plant_name'], $b['plant_name']);
    });

    jsonResponse(['success' => true, 'recommendations' => array_slice($recommendations, 0, 6)]);
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

if ($action === 'get_alerts') {
    $userId = (int) $_SESSION['user']['id'];
    $plants = getUserPlants($pdo, $userId);
    $today = (new DateTime('today'))->format('Y-m-d');
    $alerts = [];

    for ($offset = 0; $offset <= 3; $offset++) {
        $date = (new DateTime('today'))->modify('+' . $offset . ' days')->format('Y-m-d');
        foreach (buildCareTasks($plants, $date) as $task) {
            $task['is_today'] = $date === $today;
            $alerts[] = $task;
        }
    }

    usort($alerts, function ($a, $b) {
        if ($a['is_today'] !== $b['is_today']) {
            return $a['is_today'] ? -1 : 1;
        }
        if ($a['date'] !== $b['date']) {
            return $a['date'] <=> $b['date'];
        }
        return [$a['plant_name'], $a['type']] <=> [$b['plant_name'], $b['type']];
    });

    jsonResponse([
        'success' => true,
        'tasks' => $alerts
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
