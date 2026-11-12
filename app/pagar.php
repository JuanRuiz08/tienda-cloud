<?php
require_once __DIR__ . '/includes/app.php';

$user_id = requerir_usuario();

$items = items_carrito($conn, $user_id);
$total = total_carrito($items);
if (!$items) {
   $message[] = 'Tu carrito está vacío';
}

/** Resumen de los productos del pedido: "Nombre (cantidad) , Nombre (cantidad) ". */
$resumen_productos = implode(', ', array_map(fn ($i) => $i['name'] . ' (' . $i['quantity'] . ') ', $items));

if (isset($_POST['order_btn']) && $total > 0) {
   $campos = ['name', 'number', 'email', 'method', 'flat', 'street', 'city', 'country', 'pin_code'];
   $f = array_map(fn ($c) => trim((string) ($_POST[$c] ?? '')), array_combine($campos, $campos));

   $direccion = 'flat no. ' . $f['flat'] . ', ' . $f['street'] . ', ' . $f['city'] . ', ' . $f['country'] . ' - ' . $f['pin_code'];
   $fecha = date('d-M-Y');

   consulta($conn,
      'INSERT INTO `orders`(user_id, name, number, email, method, address, total_products, total_price, placed_on)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
      'issssssis',
      [$user_id, $f['name'], $f['number'], $f['email'], $f['method'], $direccion, $resumen_productos, $total, $fecha]);
   consulta($conn, 'DELETE FROM `cart` WHERE user_id = ?', 'i', [$user_id]);
   app_log('info', "pedido creado user_id=$user_id total=$total");

   // pago-paypal.php lee el pedido de la sesión.
   $_SESSION['order'] = [
      'user_id' => $user_id,
      'name' => $f['name'],
      'number' => $f['number'],
      'email' => $f['email'],
      'method' => $f['method'],
      'address' => $direccion,
      'total_products' => $resumen_productos,
      'total_price' => $total,
      'placed_on' => $fecha,
   ];

   redirigir('pago-paypal.php');
}

render_head('Verificación de compra');
include 'header.php';
render_mensajes($message ?? []);
render_encabezado('Verificación', 'Verificación');
?>

<div class="checkout-layout">

<section class="display-order">
   <?php if (!$items): ?>
      <p class="vacio">Tu carrito está vacío</p>
   <?php endif; ?>

   <?php foreach ($items as $item): ?>
   <p><?= e($item['name']) ?> <span>$<?= e($item['price']) ?> Mil × <?= (int) $item['quantity'] ?></span></p>
   <?php endforeach; ?>

   <div class="grand-total">Total general <span>$<?= $total ?> Mil</span></div>
</section>

<section class="checkout">
   <h1 class="title">Realizar Pedido</h1>
   <form action="" method="post">
      <div class="flex">
         <div class="inputBox">
            <span>Nombre:</span>
            <input type="text" name="name" required>
         </div>
         <div class="inputBox">
            <span>Número:</span>
            <input type="number" name="number" required>
         </div>
         <div class="inputBox">
            <span>Correo Electrónico:</span>
            <input type="email" name="email" required>
         </div>
         <div class="inputBox">
            <span>Método de Pago:</span>
            <select name="method" required>
               <option value="efectivo">Efectivo</option>
               <option value="tarjeta">Tarjeta</option>
            </select>
         </div>
         <div class="inputBox">
            <span>Dirección:</span>
            <input type="text" name="flat" placeholder="Apartamento" required>
            <input type="text" name="street" placeholder="Calle" required>
            <input type="text" name="city" placeholder="Ciudad" required>
            <input type="text" name="country" placeholder="País" required>
            <input type="text" name="pin_code" placeholder="Código Postal" required>
         </div>
      </div>
      <input type="submit" name="order_btn" value="Realizar pedido" class="boton-opcion">
   </form>
</section>

</div>


<?php render_cierre(); ?>
