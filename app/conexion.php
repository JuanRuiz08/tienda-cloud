<?php
// Conexión a la base de datos.
// Antes: mysqli_connect('localhost','root','','shop_db') con credenciales fijas.
// Ahora: todo viene de variables de entorno (DB_HOST, DB_USER, DB_PASS, DB_NAME).
require_once __DIR__ . '/bootstrap.php';

$conn = abrir_conexion_bd();
