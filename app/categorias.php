<?php
require_once __DIR__ . '/includes/app.php';

$user_id = requerir_usuario();

if ($aviso = procesar_agregar_al_carrito($conn, $user_id)) {
   $message[] = $aviso;
}

$categorias = ['dulceria', 'uso personal', 'escolar'];

render_head('Inicio');
include 'header.php';
render_mensajes($message ?? []);
?>

<section class="inicio">
   <div class="contenedor">
      <h3>Productos</h3>
      <a href="acerca.php" class="boton-blanco">Descubrir Más</a>
   </div>
</section>

<section class="show-products">
   <h1 class="title">Productos por Categoría</h1>

   <?php foreach ($categorias as $categoria): ?>
      <h2 class="categoria-title"><?= e(ucfirst($categoria)) ?></h2>
      <div class="caja-container">
         <?php
         $productos = consulta($conn, 'SELECT * FROM `products` WHERE category = ? LIMIT 6', 's', [$categoria]);
         render_productos($productos, 'No hay productos en esta categoría aún!');
         ?>
      </div>
   <?php endforeach; ?>

</section>

<?php render_cierre(); ?>
