<?php
header('Content-Type: application/json; charset=utf-8');

$email = trim($_POST['email'] ?? '');

if (empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Email es obligatorio.']);
    exit;
}

// Simular envío de email (en producción, usar mail() o servicio de email)
$resetToken = bin2hex(random_bytes(16)); // Token simple
// Aquí guardar token en DB con expiración, pero para demo, solo mensaje

echo json_encode(['success' => true, 'message' => 'Se ha enviado un enlace de recuperación a tu email.']);
