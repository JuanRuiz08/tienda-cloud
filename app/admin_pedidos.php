<?php
require_once __DIR__ . '/includes/app.php';

requerir_admin();

if (isset($_POST['update_order']) && !empty($_POST['update_payment'])) {
   consulta($conn, 'UPDATE `orders` SET payment_status = ? WHERE id = ?', 'si',
      [$_POST['update_payment'], (int) $_POST['order_id']]);
   $message[] = '¡El estado del pago ha sido actualizado!';
}

if (isset($_GET['delete'])) {
   consulta($conn, 'CALL eliminar_pedido(?)', 'i', [(int) $_GET['delete']]);
   redirigir('admin_pedidos.php');
}

$pedidos = consulta($conn, 'SELECT * FROM `orders`');

// Etiqueta => [campo, prefijo, sufijo]
$campos = [
   'id de usuario' => ['user_id'],
   'realizado en' => ['placed_on'],
   'nombre' => ['name'],
   'número' => ['number'],
   'correo electrónico' => ['email'],
   'dirección' => ['address'],
   'productos totales' => ['total_products'],
   'precio total' => ['total_price', '$', '/-'],
   'método de pago' => ['method'],
];

render_head('pedidos', 'admin_style.css');
include 'admin_header.php';
?>

<section class="pedidos">

   <h1 class="title">pedidos realizados</h1>

   <div class="caja-container">
      <?php if (!$pedidos): ?>
         <p class="empty">¡No hay pedidos realizados todavía!</p>
      <?php endif; ?>

      <?php foreach ($pedidos as $pedido): ?>
      <div class="caja">
         <?php foreach ($campos as $etiqueta => $spec): ?>
         <p> <?= $etiqueta ?> : <span><?= ($spec[1] ?? '') . e($pedido[$spec[0]]) . ($spec[2] ?? '') ?></span> </p>
         <?php endforeach; ?>
         <form action="" method="post">
            <input type="hidden" name="order_id" value="<?= (int) $pedido['id'] ?>">
            <select name="update_payment">
               <option value="" selected disabled><?= e($pedido['payment_status']) ?></option>
               <option value="pending">pendiente</option>
               <option value="completed">completado</option>
            </select>
            <input type="submit" value="actualizar" name="update_order" class="option-btn">
         </form>
      </div>
      <?php endforeach; ?>
   </div>

</section>

<?php render_cierre('admin_script.js', false); ?>
