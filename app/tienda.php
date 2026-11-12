<?php
require_once __DIR__ . '/includes/app.php';

$user_id = requerir_usuario();

if ($aviso = procesar_agregar_al_carrito($conn, $user_id)) {
   $message[] = $aviso;
}

$productos = consulta($conn, 'SELECT * FROM `products`');

render_head('tienda');
include 'header.php';
render_mensajes($message ?? []);
render_encabezado('Nuestra tienda', 'Tienda');
?>

<section class="show-products">

   <h1 class="title">Últimos productos</h1>

   <div class="caja-container">
      <?php render_productos($productos, '¡no se han añadido productos aún!'); ?>
   </div>

</section>

<?php render_cierre(); ?>
