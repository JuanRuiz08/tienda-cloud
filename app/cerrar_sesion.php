<?php
require_once __DIR__ . '/includes/app.php';

iniciar_sesion();
session_unset();
session_destroy();

redirigir('login.php');
