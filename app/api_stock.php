<?php
// Stock actual de todos los productos, en JSON {id: stock}. Lo consulta js/script.js cada pocos segundos
// para que las tarjetas del catálogo reflejen las compras sin recargar la página. Es público (solo lee).
require_once __DIR__ . '/includes/app.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$filas = consulta($conn, 'SELECT id, stock FROM `products`');
echo json_encode(array_map('intval', array_column($filas, 'stock', 'id')), JSON_FORCE_OBJECT);