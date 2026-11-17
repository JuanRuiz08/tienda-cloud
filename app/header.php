<?php
// Barra superior de la tienda. Se incluye desde cada página, que ya cargó includes/app.php.
// Los invitados pueden navegar; el carrito, los pedidos y el pago exigen sesión.
$user_id = usuario_actual();
$cantidad_carrito = $user_id ? (int) valor($conn, 'SELECT COUNT(*) FROM `cart` WHERE user_id = ?', 'i', [$user_id]) : 0;
?>

<header class="header">

   <div class="header-1">
      <div class="flex">
         <div class="share">
            <a href="https://www.instagram.com/papeleriaucp/" class="fab fa-instagram" aria-label="Instagram"></a>
         </div>
         <?php if ($user_id): ?>
         <p>Hola, <strong><?= e($_SESSION['user_name'] ?? '') ?></strong></p>
         <?php else: ?>
         <p>¿Nuevo aquí? <a href="login.php">Inicia sesión</a> | <a href="registro.php">Regístrate</a></p>
         <?php endif; ?>
      </div>
   </div>

   <div class="header-2">
      <div class="flex">
         <a href="inicio.php" class="logo">papeleria U.C.P.</a>

         <nav class="navbar">
            <a href="categorias.php">Categorías</a>
            <a href="inicio.php">Inicio</a>
            <a href="acerca.php">Acerca de</a>
            <a href="tienda.php">Tienda</a>
            <?php if ($user_id): ?><a href="pedidos.php">Pedidos</a><?php endif; ?>
         </nav>

         <div class="icons">
            <div id="menu-btn" class="fas fa-bars" role="button" aria-label="Menú"></div>
            <div id="search-btn" class="fas fa-search" role="button" aria-label="Buscar"></div>
            <a href="carrito.php" aria-label="Carrito">
               <i class="fas fa-shopping-cart"></i>
               <?php if ($user_id): ?>
               <span class="contador<?= $cantidad_carrito ? '' : ' vacio-carrito' ?>"><?= $cantidad_carrito ?></span>
               <?php endif; ?>
            </a>
            <?php if ($user_id): ?>
            <div id="user-btn" class="fas fa-user" role="button" aria-label="Mi cuenta"></div>
            <?php else: ?>
            <a href="login.php" class="boton acceso-btn">Ingresar</a>
            <?php endif; ?>
         </div>

         <?php if ($user_id): ?>
         <div class="usuario-caja">
            <p>nombre de usuario : <span><?= e($_SESSION['user_name'] ?? '') ?></span></p>
            <p>correo electrónico : <span><?= e($_SESSION['user_email'] ?? '') ?></span></p>
            <a href="cerrar_sesion.php" class="boton-eliminar">cerrar sesión</a>
         </div>
         <?php endif; ?>

      <!-- Buscador desplegable con vista previa (js/script.js + api_busqueda.php) -->
      <div class="buscador" id="buscador" aria-hidden="true">
         <form action="buscar_pagina.php" method="post" class="buscador-form" role="search">
            <i class="fas fa-search"></i>
            <input type="search" name="search" id="buscador-input" placeholder="Buscar productos…" autocomplete="off" aria-label="Buscar productos">
            <button type="button" class="buscador-cerrar" aria-label="Cerrar búsqueda"><i class="fas fa-times"></i></button>
         </form>
         <div class="buscador-resultados" id="buscador-resultados" aria-live="polite"></div>
      </div>
   </div>
   </div>

</header>