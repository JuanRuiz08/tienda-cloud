<?php
require_once __DIR__ . '/includes/app.php';

$user_id = requerir_usuario();

$items = items_carrito($conn, $user_id);
$total = total_carrito($items);
if (!$items) {
   $message[] = 'Tu carrito está vacío';
}

if (isset($_POST['order_btn']) && $total > 0) {
   $campos = ['name', 'number', 'email', 'method', 'flat', 'street', 'city', 'country', 'pin_code'];
   $f = array_map(fn ($c) => trim((string) ($_POST[$c] ?? '')), array_combine($campos, $campos));
   $metodo = in_array($f['method'], ['efectivo', 'tarjeta'], true) ? $f['method'] : 'efectivo';

   [$pedido_id, $aviso] = crear_pedido($conn, $user_id, $items, [
      'name' => $f['name'],
      'number' => $f['number'],
      'email' => $f['email'],
      'direccion' => 'flat no. ' . $f['flat'] . ', ' . $f['street'] . ', ' . $f['city'] . ', ' . $f['country'] . ' - ' . $f['pin_code'],
   ], $metodo);

   if ($pedido_id === null) {
      $message[] = $aviso;   // no alcanzó el stock: no se creó nada
   } else {
      correo_pedido_diferido($conn, $pedido_id, 'creado');
      // Tarjeta: se paga ahora con PayPal. Efectivo: queda pendiente y se paga al recibir el pedido.
      redirigir($metodo === 'tarjeta' ? "pago-paypal.php?pedido=$pedido_id" : "pedidos.php?nuevo=$pedido_id");
   }
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
   <p><?= e($item['name']) ?> <span><?= precio($item['price']) ?> × <?= (int) $item['quantity'] ?></span></p>
   <?php endforeach; ?>

   <div class="grand-total">Total general <span><?= precio($total) ?></span></div>
</section>

<section class="checkout">
   <h1 class="title">Realizar Pedido</h1>
   <form action="" method="post">
      <?= csrf_campo() ?>
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
               <option value="tarjeta">Tarjeta (PayPal)</option>
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
