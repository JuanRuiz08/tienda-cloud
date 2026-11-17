<?php
// Datos ligeros del panel de administración en JSON (solo con sesión de admin). Lo consulta js/admin_script.js
// para mantener al día el contador de pedidos pendientes del menú lateral en cualquier página del panel.
require_once __DIR__ . '/includes/app.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

iniciar_sesion();
if (empty($_SESSION['admin_id'])) {
   http_response_code(401);
   echo '{"error":"sin sesión"}';
   exit();
}

echo json_encode(['pendientes' => (int) valor($conn, "SELECT COUNT(*) FROM `orders` WHERE payment_status = 'pending'")]);