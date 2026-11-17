<?php
// Copia de seguridad lógica: devuelve un .sql con el esquema y los datos de las tablas de la tienda.
// Solo administradores y solo por POST con token CSRF. Se restaura con:  mysql -u USUARIO -p BASE < copia.sql
require_once __DIR__ . '/includes/app.php';

requerir_admin();
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
   redirigir('cuenta.php');
}

// Orden pensado para las claves foráneas (primero los padres).
$tablas = ['users', 'products', 'orders', 'order_items', 'cart', 'auditoria', 'config', 'schema_migrations'];

auditar($conn, 'copia_seguridad', 'Descarga de copia de seguridad');

$nombre = 'copia_papeleria_' . date('Ymd_His') . '.sql';
header('Content-Type: application/sql; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $nombre . '"');
header('Cache-Control: no-store');

echo "-- Copia de seguridad de Papelería U.C.P. — " . date('Y-m-d H:i:s') . "\n";
echo "-- Incluye datos sensibles (hashes de contraseñas, correos). Guárdala con cuidado.\n\n";
echo "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n";

$valor_sql = function (mixed $v) use ($conn): string {
   return $v === null ? 'NULL' : "'" . $conn->real_escape_string((string) $v) . "'";
};

foreach ($tablas as $tabla) {
   $existe = valor($conn, 'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?', 's', [$tabla]);
   if (!$existe) {
      continue;
   }
   $crear = $conn->query("SHOW CREATE TABLE `$tabla`")->fetch_row()[1];
   echo "-- Tabla $tabla\nDROP TABLE IF EXISTS `$tabla`;\n$crear;\n\n";

   $resultado = $conn->query("SELECT * FROM `$tabla`", MYSQLI_USE_RESULT);
   $columnas = array_map(fn ($c) => "`{$c->name}`", $resultado->fetch_fields());
   $lote = [];
   while ($fila = $resultado->fetch_row()) {
      $lote[] = '(' . implode(', ', array_map($valor_sql, $fila)) . ')';
      if (count($lote) === 100) {
         echo "INSERT INTO `$tabla` (" . implode(', ', $columnas) . ") VALUES\n" . implode(",\n", $lote) . ";\n";
         $lote = [];
      }
   }
   if ($lote) {
      echo "INSERT INTO `$tabla` (" . implode(', ', $columnas) . ") VALUES\n" . implode(",\n", $lote) . ";\n";
   }
   $resultado->free();
   echo "\n";
}

echo "SET FOREIGN_KEY_CHECKS = 1;\n";