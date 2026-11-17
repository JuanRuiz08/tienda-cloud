<?php
// Estado ligero del panel de administración en JSON (solo con sesión de admin). js/admin_script.js lo consulta
// cada pocos segundos: si "version" cambia, vuelve a pedir solo las zonas de la página que están marcadas
// como "en vivo". Así no se descarga la página completa en cada ciclo.
require_once __DIR__ . '/includes/app.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

iniciar_sesion();
if (empty($_SESSION['admin_id'])) {
   http_response_code(401);
   echo '{"error":"sin sesión"}';
   exit();
}

$estado = consulta($conn, "SELECT
      (SELECT COUNT(*) FROM `orders` WHERE payment_status = 'pending') AS pendientes,
      CONCAT_WS('-',
         (SELECT COUNT(*) FROM `orders`),
         (SELECT COALESCE(MAX(id), 0) FROM `orders`),
         (SELECT COALESCE(SUM(payment_status = 'completed'), 0) FROM `orders`),
         (SELECT COALESCE(SUM(total_price), 0) FROM `orders`),
         (SELECT COUNT(*) FROM `products`),
         (SELECT COALESCE(SUM(stock), 0) FROM `products`),
         (SELECT COALESCE(SUM(price), 0) FROM `products`),
         (SELECT COUNT(*) FROM `users`),
         (SELECT COALESCE(MAX(id), 0) FROM `auditoria`)
      ) AS version")[0];

echo json_encode(['pendientes' => (int) $estado['pendientes'], 'version' => md5($estado['version'])]);