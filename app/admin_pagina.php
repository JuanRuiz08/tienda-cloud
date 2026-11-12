<?php
require_once __DIR__ . '/includes/app.php';

requerir_admin();

// Cada tarjeta: [valor, etiqueta]. Los totales son sumas de pedidos (en miles); el resto son conteos.
$total_pendientes = (int) valor($conn, 'SELECT COALESCE(SUM(total_price), 0) FROM `orders` WHERE payment_status = ?', 's', ['pending']);
$total_completados = (int) valor($conn, 'SELECT COALESCE(SUM(total_price), 0) FROM `orders` WHERE payment_status = ?', 's', ['completed']);

$tarjetas = [
   ['$' . $total_pendientes . ' Mil', 'total pendientes'],
   ['$' . $total_completados . ' Mil', 'pagos completados'],
   [valor($conn, 'SELECT COUNT(*) FROM `orders`'), 'Pedidos realizados'],
   [valor($conn, 'SELECT COUNT(*) FROM `products`'), 'productos añadidos'],
   [valor($conn, 'SELECT COUNT(*) FROM `users` WHERE user_type = ?', 's', ['user']), 'usuarios normales'],
   [valor($conn, 'SELECT COUNT(*) FROM `users` WHERE user_type = ?', 's', ['admin']), 'Administradores'],
   [valor($conn, 'SELECT COUNT(*) FROM `users`'), 'total cuentas'],
];

render_head('panel de administración', 'admin_style.css');
include 'admin_header.php';
?>

<section class="dashboard">

   <h1 class="title">Panel</h1>

   <div class="caja-container">
      <?php foreach ($tarjetas as [$cifra, $etiqueta]): ?>
      <div class="caja">
         <h3><?= e($cifra) ?></h3>
         <p><?= e($etiqueta) ?></p>
      </div>
      <?php endforeach; ?>
   </div>

</section>

<?php render_cierre('admin_script.js', false); ?>
