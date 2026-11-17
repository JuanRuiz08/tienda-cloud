<?php
require_once __DIR__ . '/includes/app.php';

$user_id = requerir_usuario();

if ($aviso = procesar_agregar_al_carrito($conn, $user_id)) {
   $message[] = $aviso;
}

if (isset($_GET['delete'])) {
   // Solo se puede quitar un producto del propio carrito.
   consulta($conn, 'DELETE FROM `cart` WHERE id = ? AND user_id = ?', 'ii', [(int) $_GET['delete'], $user_id]);
   redirigir('carrito.php');
}

if (isset($_GET['delete_all'])) {
   consulta($conn, 'DELETE FROM `cart` WHERE user_id = ?', 'i', [$user_id]);
   redirigir('carrito.php');
}

if (isset($_POST['update_cart'])) {
   $fila = consulta($conn,
      'SELECT COALESCE(p.stock, 0) AS stock
       FROM `cart` c LEFT JOIN `products` p ON p.name = c.name
       WHERE c.id = ? AND c.user_id = ?',
      'ii', [(int) $_POST['cart_id'], $user_id])[0] ?? null;

   if ($fila !== null) {
      $pedida = max(1, (int) $_POST['cart_quantity']);
      $tope = max(1, (int) $fila['stock']);          // nunca más de lo que hay en stock
      consulta($conn, 'UPDATE `cart` SET quantity = ? WHERE id = ? AND user_id = ?', 'iii',
         [min($pedida, $tope), (int) $_POST['cart_id'], $user_id]);
      $message[] = $pedida > $tope
         ? "Solo hay $tope disponibles: ajustamos la cantidad."
         : '¡Cantidad del carrito actualizada!';
   }
}

$items = items_carrito($conn, $user_id);
$total = total_carrito($items);
$sin_stock = items_sin_stock($items);   // el stock pudo bajar desde que se añadió al carrito

render_head('Carrito');
include 'header.php';
render_mensajes($message ?? []);
render_encabezado('Carrito de compras', 'Carrito');
?>

<section class="carrito-de-compras">

   <h1 class="title">Productos añadidos</h1>

   <div class="carrito-layout">

      <div class="caja-container">
         <?php if (!$items): ?>
            <p class="vacio">Tu carrito está vacío. <a href="tienda.php"><strong>Ir a la tienda</strong></a></p>
         <?php endif; ?>

         <?php foreach ($items as $item): ?>
         <div class="caja">
            <img src="uploaded_img/<?= e($item['image']) ?>" alt="<?= e($item['name']) ?>">
            <div class="detalle">
               <div class="name"><?= e($item['name']) ?></div>
               <div class="price"><?= precio($item['price']) ?> c/u</div>
               <form action="" method="post">
                  <input type="hidden" name="cart_id" value="<?= (int) $item['id'] ?>">
                  <input type="number" min="1" max="<?= max((int) $item['stock'], 1) ?>" name="cart_quantity" value="<?= (int) $item['quantity'] ?>" aria-label="Cantidad">
                  <input type="submit" name="update_cart" value="Actualizar" class="boton-opcion">
               </form>
               <?php if ((int) $item['quantity'] > (int) $item['stock']): ?>
               <p class="aviso-stock"><?= (int) $item['stock'] > 0 ? 'Solo quedan ' . (int) $item['stock'] . ' disponibles.' : 'Producto agotado: quítalo para continuar.' ?></p>
               <?php endif; ?>
            </div>
            <div class="sub-total">Subtotal <span><?= precio($item['quantity'] * $item['price']) ?></span></div>
            <a href="carrito.php?delete=<?= (int) $item['id'] ?>" class="fas fa-times" aria-label="Quitar" onclick="return confirm('¿Eliminar esto del carrito?');"></a>
         </div>
         <?php endforeach; ?>
      </div>

      <div class="total-carrito">
         <p>Total general <span><?= precio($total) ?></span></p>
         <div class="flex">
            <a href="pagar.php" class="boton <?= ($total > 0 && !$sin_stock) ? '' : 'disabled' ?>">Proceder al pago</a>
            <a href="tienda.php" class="boton-opcion">Continuar comprando</a>
         </div>
      </div>

   </div>

</section>

<?php render_cierre(); ?>
