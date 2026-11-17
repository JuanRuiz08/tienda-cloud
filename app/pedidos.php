<?php
require_once __DIR__ . '/includes/app.php';

$user_id = requerir_usuario();

$pedidos = consulta($conn, 'CALL getUserOrders(?)', 'i', [$user_id]);

if (isset($_GET['pagado'])) {
   $message[] = '¡Pago recibido! Tu pedido quedó pagado.';
} elseif (isset($_GET['nuevo'])) {
   $message[] = 'Pedido registrado. Pagarás en efectivo al recibirlo.';
}

// Etiqueta => campo (el id, la fecha, el estado y el total se muestran aparte).
$detalles = [
   'Nombre' => 'name',
   'Número' => 'number',
   'Correo' => 'email',
   'Método' => 'method',
   'Dirección' => 'address',
   'Productos' => 'total_products',
];

$estados = ['pending' => 'Pendiente', 'completed' => 'Completado'];

render_head('Mis Pedidos');
include 'header.php';
render_mensajes($message ?? []);
render_encabezado('Mis pedidos', 'Pedidos');
?>

<section class="pedidos">

   <div class="caja-container" data-vivo="pedidos">
      <?php if (!$pedidos): ?>
         <p class="vacio">No has realizado pedidos aún. <a href="tienda.php"><strong>Ir a la tienda</strong></a></p>
      <?php endif; ?>

      <?php foreach ($pedidos as $pedido): ?>
      <article class="caja">
         <header>
            <div>
               <strong>Pedido #<?= (int) $pedido['id'] ?></strong>
               <small><?= e($pedido['placed_on']) ?></small>
            </div>
            <span class="estado estado-<?= e($pedido['payment_status']) ?>"><?= e($estados[$pedido['payment_status']] ?? $pedido['payment_status']) ?></span>
         </header>
         <dl>
            <?php foreach ($detalles as $etiqueta => $campo): ?>
            <dt><?= $etiqueta ?></dt>
            <dd><?= e($pedido[$campo]) ?></dd>
            <?php endforeach; ?>
         </dl>
         <div class="total">Total <strong><?= precio($pedido['total_price']) ?></strong></div>
         <?php if ($pedido['payment_status'] === 'pending' && $pedido['method'] === 'tarjeta'): ?>
         <a href="pago-paypal.php?pedido=<?= (int) $pedido['id'] ?>" class="boton pagar-ahora">Pagar ahora</a>
         <?php endif; ?>
      </article>
      <?php endforeach; ?>
   </div>

</section>

<?php render_cierre(); ?>
