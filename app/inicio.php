<?php
require_once __DIR__ . '/includes/app.php';

$user_id = requerir_usuario();

if ($aviso = procesar_agregar_al_carrito($conn, $user_id)) {
   $message[] = $aviso;
}

$productos = consulta($conn, 'SELECT * FROM `products` LIMIT 6');

render_head('inicio');
include 'header.php';
render_mensajes($message ?? []);
?>

<section class="inicio">
   <div class="contenedor">
      <h3>productos</h3>
      <a href="acerca.php" class="boton-blanco">Descubrir Más</a>
   </div>
</section>

<section class="show-products">

   <h1 class="title">Los productos más vendidos</h1>

   <div class="caja-container">
      <?php render_productos($productos, 'Todavía no se han añadido productos!'); ?>
   </div>

</section>

<?php render_cierre(); ?>
