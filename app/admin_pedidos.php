<?php
require_once __DIR__ . '/includes/app.php';

requerir_admin();

$estados = ['pending' => 'Pendiente', 'completed' => 'Completado'];

if (isset($_POST['update_order']) && isset($estados[$_POST['update_payment'] ?? ''])) {
   consulta($conn, 'UPDATE `orders` SET payment_status = ? WHERE id = ?', 'si',
      [$_POST['update_payment'], (int) $_POST['order_id']]);
   $message[] = '¡El estado del pedido #' . (int) $_POST['order_id'] . ' ahora es «' . $estados[$_POST['update_payment']] . '»!';
}

if (isset($_GET['delete'])) {
   consulta($conn, 'CALL eliminar_pedido(?)', 'i', [(int) $_GET['delete']]);
   redirigir('admin_pedidos.php');
}

// Filtro por estado: ?estado=pending | completed (por defecto, todos).
$filtro = isset($estados[$_GET['estado'] ?? '']) ? $_GET['estado'] : 'todos';

$conteo = array_column(consulta($conn, 'SELECT payment_status, COUNT(*) AS n FROM `orders` GROUP BY payment_status'), 'n', 'payment_status');
$totales = ['todos' => array_sum($conteo), 'pending' => (int) ($conteo['pending'] ?? 0), 'completed' => (int) ($conteo['completed'] ?? 0)];

// Los pendientes van primero (son los que requieren atención); dentro de cada grupo, el más reciente arriba.
$pedidos = consulta($conn, 'SELECT * FROM `orders` ORDER BY (payment_status = \'pending\') DESC, id DESC');
if ($filtro !== 'todos') {
   $pedidos = array_values(array_filter($pedidos, fn ($p) => $p['payment_status'] === $filtro));
}

// Etiqueta => [campo] (o [campo, 'precio'] para mostrarlo como dinero)
$campos = [
   'id de usuario' => ['user_id'],
   'nombre' => ['name'],
   'número' => ['number'],
   'correo electrónico' => ['email'],
   'dirección' => ['address'],
   'productos' => ['total_products'],
   'precio total' => ['total_price', 'precio'],
   'método de pago' => ['method'],
];

$pestanas = ['todos' => 'Todos', 'pending' => 'Pendientes', 'completed' => 'Completados'];

render_head('pedidos', 'admin_style.css');
include 'admin_header.php';
?>

<section class="pedidos">

   <h1 class="title">pedidos realizados</h1>

   <nav class="filtros" aria-label="Filtrar por estado">
      <?php foreach ($pestanas as $clave => $texto): ?>
      <a href="admin_pedidos.php<?= $clave === 'todos' ? '' : '?estado=' . $clave ?>" class="<?= $filtro === $clave ? 'activo' : '' ?>">
         <?= $texto ?> <span data-total="<?= $clave ?>"><?= $totales[$clave] ?></span>
      </a>
      <?php endforeach; ?>
   </nav>

   <div class="caja-container" data-vivo>
      <?php if (!$pedidos): ?>
         <p class="empty">¡No hay pedidos <?= $filtro === 'todos' ? 'realizados todavía' : 'en este estado' ?>!</p>
      <?php endif; ?>

      <?php foreach ($pedidos as $pedido): ?>
      <div class="caja <?= $pedido['payment_status'] === 'completed' ? 'completado' : 'pendiente' ?>" id="pedido-<?= (int) $pedido['id'] ?>">
         <header>
            <div>
               <strong>Pedido #<?= (int) $pedido['id'] ?></strong>
               <small><?= e($pedido['placed_on']) ?></small>
            </div>
            <span class="estado estado-<?= e($pedido['payment_status']) ?>"><?= e($estados[$pedido['payment_status']] ?? $pedido['payment_status']) ?></span>
         </header>

         <?php foreach ($campos as $etiqueta => $spec): ?>
         <p> <?= $etiqueta ?> : <span><?= ($spec[1] ?? null) === 'precio' ? precio($pedido[$spec[0]]) : e($pedido[$spec[0]]) ?></span> </p>
         <?php endforeach; ?>

         <form action="" method="post">
            <input type="hidden" name="order_id" value="<?= (int) $pedido['id'] ?>">
            <select name="update_payment" aria-label="Estado del pago">
               <?php foreach ($estados as $valor => $texto): ?>
               <option value="<?= $valor ?>"<?= $pedido['payment_status'] === $valor ? ' selected' : '' ?>><?= $texto ?></option>
               <?php endforeach; ?>
            </select>
            <input type="submit" value="actualizar" name="update_order" class="option-btn">
         </form>
      </div>
      <?php endforeach; ?>
   </div>

</section>

<?php render_cierre('admin_script.js', false); ?>
