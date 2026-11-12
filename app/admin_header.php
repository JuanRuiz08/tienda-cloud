<?php render_mensajes($message ?? []); ?>

<header class="header">

   <div class="flex">

      <a href="admin_pagina.php" class="logo"><span>Admin<b>Panel</b></span></a>

      <nav class="navbar">
         <a href="admin_pagina.php">inicio</a>
         <a href="admin_productos.php">productos</a>
         <a href="admin_pedidos.php">pedidos</a>
         <a href="admin_usuarios.php">usuarios</a>
      </nav>

      <div class="icons">
         <div id="menu-btn" class="fas fa-bars"></div>
         <div id="user-btn" class="fas fa-user"></div>
      </div>

      <div class="account-box">
         <p>username : <span><?= e($_SESSION['admin_name'] ?? '') ?></span></p>
         <p>email : <span><?= e($_SESSION['admin_email'] ?? '') ?></span></p>
         <a href="pagar.php" class="boton-eliminar">pagar</a>
         <div>new <a href="login.php">login</a> | <a href="registro.php">registro</a></div>
      </div>

   </div>

</header>
