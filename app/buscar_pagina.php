<?php
require_once __DIR__ . '/includes/app.php';

$user_id = requerir_usuario();

if ($aviso = procesar_agregar_al_carrito($conn, $user_id)) {
   $message[] = $aviso;
}

// Se busca en nombre y descripción.
$termino = trim((string) ($_POST['search'] ?? ''));
$resultados = [];
if ($termino !== '') {
   $comodin = '%' . $termino . '%';
   $resultados = consulta($conn, 'SELECT * FROM `products` WHERE name LIKE ? OR description LIKE ?', 'ss', [$comodin, $comodin]);
}

render_head('página de búsqueda');
include 'header.php';
render_mensajes($message ?? []);
render_encabezado('productos', 'búsqueda');
?>

<section class="formulario-busqueda">
   <form action="" method="post">
      <input type="text" name="search" placeholder="buscar productos..." class="caja" value="<?= e($termino) ?>">
      <input type="submit" name="submit" value="buscar" class="boton">
   </form>
</section>

<section class="productos" style="padding-top: 0;">

   <div class="caja-container">
      <?php
      if ($termino === '') {
         echo '<p class="vacio">¡Busca algo!</p>';
      } else {
         render_productos($resultados, '¡No se han encontrado resultados!');
      }
      ?>
   </div>

</section>

<?php render_cierre(); ?>
