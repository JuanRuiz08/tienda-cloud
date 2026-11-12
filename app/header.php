<?php
// Barra superior de la tienda. Se incluye desde cada página, que ya cargó includes/app.php.
$user_id = requerir_usuario();
$cantidad_carrito = (int) valor($conn, 'SELECT COUNT(*) FROM `cart` WHERE user_id = ?', 'i', [$user_id]);
?>

<header class="header">

   <div class="header-1">
      <div class="flex">
         <div class="share">
            <a href="https://www.instagram.com/papeleriaucp/" class="fab fa-instagram"></a>
         </div>
         <p> nuevo <a href="login.php">iniciar sesión</a> | <a href="registro.php">registrarse</a> </p>
      </div>
   </div>

   <div class="header-2">
      <div class="flex">
         <a href="inicio.php" class="logo">papeleria U.C.P.</a>

         <nav class="navbar">
            <a href="categorias.php">Categorías</a>
            <a href="inicio.php">inicio</a>
            <a href="acerca.php">acerca de</a>
            <a href="tienda.php">tienda</a>
            <a href="pedidos.php">pedidos</a>
         </nav>

         <div class="icons">
            <div id="menu-btn" class="fas fa-bars"></div>
            <a href="buscar_pagina.php" class="fas fa-search"></a>
            <div id="user-btn" class="fas fa-user "></div>
            <a href="carrito.php"> <i class="fas fa-shopping-cart"></i> <span>(<?= $cantidad_carrito ?>)</span> </a>
         </div>

         <div class="usuario-caja">
            <p>nombre de usuario : <span><?= e($_SESSION['user_name'] ?? '') ?></span></p>
            <p>correo electrónico : <span><?= e($_SESSION['user_email'] ?? '') ?></span></p>
            <a href="cerrar_sesion.php" class="boton-eliminar">cerrar sesión</a>
         </div>
      </div>
   </div>

</header>
