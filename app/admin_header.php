<?php
// Cabecera del panel de administración: barra superior + menú lateral desplegable (js/admin_script.js).
$admin_nombre = (string) ($_SESSION['admin_name'] ?? 'Administrador');
$admin_correo = (string) ($_SESSION['admin_email'] ?? '');
$inicial = mb_strtoupper(mb_substr($admin_nombre, 0, 1)) ?: 'A';
$pendientes = (int) valor($conn, "SELECT COUNT(*) FROM `orders` WHERE payment_status = 'pending'");
$pagina_actual = basename($_SERVER['SCRIPT_NAME']);

// [archivo, icono, texto, insignia (null = sin insignia)]
$menu = [
   ['admin_pagina.php', 'fa-chart-pie', 'Panel', null],
   ['admin_productos.php', 'fa-boxes', 'Productos', null],
   ['admin_pedidos.php', 'fa-receipt', 'Pedidos', $pendientes],
   ['admin_usuarios.php', 'fa-users', 'Usuarios', null],
];

render_mensajes($message ?? []);
?>

<header class="header">
   <div class="barra">
      <button type="button" id="menu-btn" class="menu-btn" aria-label="Abrir menú" aria-expanded="false" aria-controls="menu-lateral">
         <i class="fas fa-bars"></i>
      </button>

      <a href="admin_pagina.php" class="logo"><span>Admin<b>Panel</b></span></a>

      <a href="inicio.php" target="_blank" rel="noopener" class="ver-tienda"><i class="fas fa-store"></i> <span>Ver tienda</span></a>

      <div class="chip-usuario" title="<?= e($admin_correo) ?>">
         <span class="avatar"><?= e($inicial) ?></span>
         <span class="nombre"><?= e($admin_nombre) ?></span>
      </div>
   </div>
</header>

<div class="scrim" id="scrim"></div>

<aside class="menu-lateral" id="menu-lateral" aria-label="Menú del panel" aria-hidden="true">
   <div class="menu-cabecera">
      <a href="admin_pagina.php" class="logo"><span>Admin<b>Panel</b></span></a>
      <button type="button" class="menu-cerrar" aria-label="Cerrar menú"><i class="fas fa-times"></i></button>
   </div>

   <p class="menu-etiqueta">Gestión</p>
   <nav class="menu-nav">
      <?php foreach ($menu as [$archivo, $icono, $texto, $insignia]): ?>
      <a href="<?= $archivo ?>" class="<?= $pagina_actual === $archivo ? 'activo' : '' ?>"<?= $pagina_actual === $archivo ? ' aria-current="page"' : '' ?>>
         <i class="fas <?= $icono ?>"></i>
         <span><?= $texto ?></span>
         <?php if ($insignia !== null): ?>
         <span class="insignia<?= $insignia ? '' : ' cero' ?>" data-pendientes title="Pedidos pendientes"><?= $insignia ?></span>
         <?php endif; ?>
      </a>
      <?php endforeach; ?>
   </nav>

   <div class="menu-pie">
      <div class="menu-usuario">
         <span class="avatar"><?= e($inicial) ?></span>
         <div>
            <strong><?= e($admin_nombre) ?></strong>
            <small><?= e($admin_correo) ?></small>
         </div>
      </div>
      <a href="inicio.php" target="_blank" rel="noopener" class="menu-enlace"><i class="fas fa-store"></i> Ver tienda</a>
      <a href="cerrar_sesion.php" class="salir"><i class="fas fa-sign-out-alt"></i> Cerrar sesión</a>
   </div>
</aside>
