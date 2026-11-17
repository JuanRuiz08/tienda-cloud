<?php
/**
 * Punto de entrada común: cada página hace  require_once __DIR__ . '/includes/app.php';
 * Carga los helpers, abre una única conexión a la base de datos ($conn) y rechaza los POST sin token CSRF.
 */
require_once __DIR__ . '/sesion.php';
require_once __DIR__ . '/vistas.php';
require_once __DIR__ . '/datos.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/seguridad.php';
require_once __DIR__ . '/auditoria.php';
require_once __DIR__ . '/tienda.php';
require_once __DIR__ . '/tasa.php';
require_once __DIR__ . '/correo.php';
require_once __DIR__ . '/../conexion.php';

csrf_exigir();