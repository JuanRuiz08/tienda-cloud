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
      <span class="kicker">Papelería U.C.P.</span>
      <h3>Todo para tu estudio, en un solo lugar</h3>
      <p>Útiles escolares, dulcería y artículos de uso personal, sin salir del campus.</p>
      <div class="acciones">
         <a href="tienda.php" class="boton">Ver la tienda</a>
         <a href="acerca.php" class="boton-blanco">Descubrir más</a>
      </div>
   </div>
</section>

<section class="show-products">

   <h1 class="title">Los productos más vendidos</h1>

   <div class="caja-container">
      <?php render_productos($productos, 'Todavía no se han añadido productos!'); ?>
   </div>

</section>

<?php render_cierre(); ?>
