<?php
header('Content-Type: text/html; charset=utf-8');

$db = new SQLite3('plantita.db');

echo "<!DOCTYPE html>
<html lang='es'>
<head>
  <meta charset='UTF-8'>
  <title>Base de Datos PlantITa</title>
  <style>
    body { font-family: Arial; margin: 20px; background: #f5f5f5; }
    h1 { color: #007aef; }
    h2 { color: #dfb160; margin-top: 30px; }
    table { border-collapse: collapse; width: 100%; background: white; margin: 20px 0; }
    th { background: #007aef; color: white; padding: 10px; text-align: left; }
    td { border-bottom: 1px solid #ddd; padding: 10px; }
    tr:hover { background: #f9f9f9; }
  </style>
</head>
<body>
  <h1>Base de Datos PlantITa</h1>";

echo "<h2>Tabla: usuarios</h2>";
$result = $db->query("SELECT * FROM usuarios");
echo "<table><tr><th>ID</th><th>Nombre</th><th>Email</th><th>Hash</th><th>Creado</th></tr>";
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
  $hash = substr($row['contrasena_hash'], 0, 12) . '...';
  echo "<tr><td>{$row['id']}</td><td>{$row['nombre']}</td><td>{$row['email']}</td><td>{$hash}</td><td>{$row['creado_en']}</td></tr>";
}
echo "</table>";

echo "<h2>Tabla: plantas (primeras 10)</h2>";
$result = $db->query("SELECT * FROM plantas LIMIT 10");
echo "<table><tr><th>ID</th><th>Nombre</th><th>Tipo</th><th>Cuidado</th></tr>";
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
  echo "<tr><td>{$row['id']}</td><td>{$row['nombre']}</td><td>{$row['tipo']}</td><td>{$row['cuidado']}</td></tr>";
}
echo "</table>";

$count = $db->querySingle("SELECT COUNT(*) FROM plantas");
echo "<p><strong>Total de plantas en catalogo: $count</strong></p>";

echo "<h2>Plantas por usuario</h2>";
$result = $db->query("
  SELECT pu.id, u.email, p.nombre AS planta, pu.agregado_en
  FROM plantas_usuario pu
  JOIN usuarios u ON u.id = pu.usuario_id
  JOIN plantas p ON p.id = pu.planta_id
  ORDER BY pu.id
");
echo "<table><tr><th>ID</th><th>Usuario</th><th>Planta</th><th>Agregada</th></tr>";
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
  echo "<tr><td>{$row['id']}</td><td>{$row['email']}</td><td>{$row['planta']}</td><td>{$row['agregado_en']}</td></tr>";
}
echo "</table>";

echo "</body></html>";
$db->close();
?>
