<?php
require_once __DIR__ . '/includes/app.php';

$user_id = requerir_usuario();

if ($aviso = procesar_agregar_al_carrito($conn, $user_id)) {
   $message[] = $aviso;
}

if (isset($_GET['delete'])) {
   consulta($conn, 'CALL eliminar_producto_carrito(?)', 'i', [(int) $_GET['delete']]);
   redirigir('carrito.php');
}

if (isset($_GET['delete_all'])) {
   consulta($conn, 'DELETE FROM `cart` WHERE user_id = ?', 'i', [$user_id]);
   redirigir('carrito.php');
}

if (isset($_POST['update_cart'])) {
   consulta($conn, 'CALL actualizar_cantidad_carrito(?, ?)', 'ii',
      [(int) $_POST['cart_id'], max(1, (int) $_POST['cart_quantity'])]);
   $message[] = '¡Cantidad del carrito actualizada!';
}

$items = items_carrito($conn, $user_id);
$total = total_carrito($items);

render_head('Carrito');
include 'header.php';
render_mensajes($message ?? []);
render_encabezado('Carrito de Compras', 'Carrito');
?>

<section class="carrito-de-compras">

   <h1 class="title">Productos Añadidos</h1>

   <div class="caja-container">
      <?php if (!$items): ?>
         <p class="vacio">Tu carrito está vacío</p>
      <?php endif; ?>

      <?php foreach ($items as $item): ?>
      <div class="caja">
         <a href="carrito.php?delete=<?= (int) $item['id'] ?>" class="fas fa-times" onclick="return confirm('¿Eliminar esto del carrito?');"></a>
         <img src="uploaded_img/<?= e($item['image']) ?>" alt="">
         <div class="name"><?= e($item['name']) ?></div>
         <div class="price">$<?= e($item['price']) ?> Mil</div>
         <form action="" method="post">
            <input type="hidden" name="cart_id" value="<?= (int) $item['id'] ?>">
            <input type="number" min="1" name="cart_quantity" value="<?= (int) $item['quantity'] ?>">
            <input type="submit" name="update_cart" value="actualizar" class="boton-opcion">
         </form>
         <div class="sub-total"> Subtotal : <span>$<?= $item['quantity'] * $item['price'] ?> Mil</span> </div>
      </div>
      <?php endforeach; ?>
   </div>

   <div class="total-carrito">
      <p>Total general: <span>$<?= $total ?> Mil</span></p>
      <div class="flex">
         <a href="tienda.php" class="boton-opcion">Continuar comprando</a>
         <a href="pagar.php" class="boton <?= $total > 1 ? '' : 'disabled' ?>">Proceder al pago</a>
      </div>
   </div>

</section>

<?php render_cierre(); ?>
