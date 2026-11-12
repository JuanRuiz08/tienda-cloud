<?php
/**
 * Punto de entrada común: cada página hace  require_once __DIR__ . '/includes/app.php';
 * Carga los helpers y abre una única conexión a la base de datos ($conn).
 */
require_once __DIR__ . '/sesion.php';
require_once __DIR__ . '/vistas.php';
require_once __DIR__ . '/datos.php';
require_once __DIR__ . '/tienda.php';
require_once __DIR__ . '/../conexion.php';
