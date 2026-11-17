<?php
// "Versión" de los pedidos del cliente en sesión (JSON). js/script.js la consulta en "Mis pedidos" y solo
// vuelve a pedir la lista cuando cambia (un pedido nuevo o un cambio de estado).
require_once __DIR__ . '/includes/app.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$user_id = usuario_actual();
if ($user_id === null) {
   http_response_code(401);
   echo '{"error":"sin sesión"}';
   exit();
}

$v = valor($conn, "SELECT CONCAT_WS('-', COUNT(*), COALESCE(MAX(id), 0), COALESCE(SUM(payment_status = 'completed'), 0), COALESCE(SUM(payment_status = 'cancelled'), 0))
                   FROM `orders` WHERE user_id = ?", 'i', [$user_id]);
echo json_encode(['version' => md5((string) $v)]);