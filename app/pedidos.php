<?php
require_once __DIR__ . '/includes/app.php';

$user_id = requerir_usuario();

$pedidos = consulta($conn, 'CALL getUserOrders(?)', 'i', [$user_id]);

// Etiqueta => [campo, prefijo, sufijo]
$campos = [
   'Pedido ID' => ['id'],
   'Nombre' => ['name'],
   'Número' => ['number'],
   'Correo' => ['email'],
   'Método' => ['method'],
   'Dirección' => ['address'],
   'Productos Totales' => ['total_products'],
   'Precio Total' => ['total_price', '$', ' Mil'],
   'Pedido Realizado en' => ['placed_on'],
   'Estado del Pago' => ['payment_status'],
];

render_head('Mis Pedidos');
include 'header.php';
?>

<section class="pedidos">

   <h1 class="title">Mis Pedidos</h1>

   <div class="caja-container">
      <?php if (!$pedidos): ?>
         <p class="vacio">No has realizado pedidos aún</p>
      <?php endif; ?>

      <?php foreach ($pedidos as $pedido): ?>
      <div class="caja">
         <?php foreach ($campos as $etiqueta => $spec): ?>
         <p> <?= $etiqueta ?>: <span><?= ($spec[1] ?? '') . e($pedido[$spec[0]]) . ($spec[2] ?? '') ?></span> </p>
         <?php endforeach; ?>
      </div>
      <?php endforeach; ?>
   </div>

</section>

<?php render_cierre(); ?>
