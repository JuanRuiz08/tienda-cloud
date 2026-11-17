<?php
require_once __DIR__ . '/includes/app.php';

requerir_admin();

$estados = ['pending' => 'Pendiente', 'completed' => 'Completado', 'cancelled' => 'Cancelado'];
$editables = ['pending' => 'Pendiente', 'completed' => 'Completado'];   // un pedido cancelado no se reabre: devolvió su stock

if (isset($_POST['update_order']) && isset($editables[$_POST['update_payment'] ?? ''])) {
   $id = (int) $_POST['order_id'];
   consulta($conn, "UPDATE `orders` SET payment_status = ? WHERE id = ? AND payment_status <> 'cancelled'", 'si', [$_POST['update_payment'], $id]);
   auditar($conn, 'pedido_estado', "Pedido #$id → " . $editables[$_POST['update_payment']]);
   if ($_POST['update_payment'] === 'completed') {
      correo_pedido_diferido($conn, $id, 'completado');
   }
   $message[] = "¡El estado del pedido #$id ahora es «" . $editables[$_POST['update_payment']] . '»!';
}

if (isset($_POST['cancelar'])) {
   $id = (int) $_POST['cancelar'];
   $aviso = cancelar_pedido($conn, $id);
   auditar($conn, 'pedido_cancelado', $aviso);
   $message[] = $aviso;
}

if (isset($_POST['eliminar'])) {
   $id = (int) $_POST['eliminar'];
   $aviso = eliminar_pedido($conn, $id);
   auditar($conn, 'pedido_eliminado', $aviso);
   $message[] = $aviso;
}

// Filtro por estado: ?estado=pending | completed | cancelled (por defecto, todos).
$filtro = isset($estados[$_GET['estado'] ?? '']) ? $_GET['estado'] : 'todos';

$conteo = array_column(consulta($conn, 'SELECT payment_status, COUNT(*) AS n FROM `orders` GROUP BY payment_status'), 'n', 'payment_status');
$totales = ['todos' => array_sum($conteo)];
foreach ($estados as $clave => $_) {
   $totales[$clave] = (int) ($conteo[$clave] ?? 0);
}

// Los pendientes van primero (son los que requieren atención); dentro de cada grupo, el más reciente arriba.
$pag = paginar($totales[$filtro], 12);
$donde = $filtro === 'todos' ? '' : 'WHERE payment_status = ?';
$sql = "SELECT * FROM `orders` $donde ORDER BY (payment_status = 'pending') DESC, id DESC LIMIT ? OFFSET ?";
$pedidos = $filtro === 'todos'
   ? consulta($conn, $sql, 'ii', [$pag['por'], $pag['desde']])
   : consulta($conn, $sql, 'sii', [$filtro, $pag['por'], $pag['desde']]);

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
// Una venta en tienda no tiene cuenta, teléfono, correo ni dirección: solo se muestra lo que sí tiene.
$campos_tienda = array_intersect_key($campos, array_flip(['nombre', 'productos', 'precio total', 'método de pago']));

$pestanas = ['todos' => 'Todos', 'pending' => 'Pendientes', 'completed' => 'Completados', 'cancelled' => 'Cancelados'];
$clases = ['pending' => 'pendiente', 'completed' => 'completado', 'cancelled' => 'cancelado'];

render_head('pedidos', 'admin_style.css');
include 'admin_header.php';
?>

<section class="pedidos">

   <h1 class="title">pedidos realizados</h1>

   <nav class="filtros" aria-label="Filtrar por estado" data-vivo="filtros">
      <?php foreach ($pestanas as $clave => $texto): ?>
      <a href="admin_pedidos.php<?= $clave === 'todos' ? '' : '?estado=' . $clave ?>" class="<?= $filtro === $clave ? 'activo' : '' ?>">
         <?= $texto ?> <span data-total="<?= $clave ?>"><?= $totales[$clave] ?></span>
      </a>
      <?php endforeach; ?>
   </nav>

   <div class="caja-container" data-vivo="pedidos">
      <?php if (!$pedidos): ?>
         <p class="empty">¡No hay pedidos <?= $filtro === 'todos' ? 'realizados todavía' : 'en este estado' ?>!</p>
      <?php endif; ?>

      <?php foreach ($pedidos as $pedido): ?>
      <?php $estado = $pedido['payment_status']; $en_tienda = $pedido['origin'] === 'presencial'; ?>
      <div class="caja <?= $clases[$estado] ?? 'pendiente' ?>" id="pedido-<?= (int) $pedido['id'] ?>">
         <header>
            <div>
               <strong>Pedido #<?= (int) $pedido['id'] ?><?= $en_tienda ? '<span class="origen-tienda">En tienda</span>' : '' ?></strong>
               <small><?= e(fecha_corta($pedido['created_at']) ?: $pedido['placed_on']) ?></small>
            </div>
            <span class="estado estado-<?= e($estado) ?>"><?= e($estados[$estado] ?? $estado) ?></span>
         </header>

         <?php foreach ($en_tienda ? $campos_tienda : $campos as $etiqueta => $spec): ?>
         <p> <?= $etiqueta ?> : <span><?= ($spec[1] ?? null) === 'precio' ? precio($pedido[$spec[0]]) : e($pedido[$spec[0]]) ?></span> </p>
         <?php endforeach; ?>

         <?php if ($estado !== 'cancelled'): ?>
         <form action="" method="post">
            <?= csrf_campo() ?>
            <input type="hidden" name="order_id" value="<?= (int) $pedido['id'] ?>">
            <select name="update_payment" aria-label="Estado del pago">
               <?php foreach ($editables as $valor => $texto): ?>
               <option value="<?= $valor ?>"<?= $estado === $valor ? ' selected' : '' ?>><?= $texto ?></option>
               <?php endforeach; ?>
            </select>
            <input type="submit" value="actualizar" name="update_order" class="option-btn">
         </form>
         <?php endif; ?>

         <?= match ($estado) {
            'pending' => form_borrar('cancelar', (int) $pedido['id'], 'Cancelar pedido', '¿Cancelar el pedido #' . (int) $pedido['id'] . '? Quedará registrado como cancelado y el stock volverá a la tienda.', 'boton-eliminar'),
            'cancelled' => form_borrar('eliminar', (int) $pedido['id'], 'Eliminar', '¿Eliminar el pedido #' . (int) $pedido['id'] . ' del historial? Ya estaba cancelado: el stock no cambia.', 'boton-eliminar'),
            default => form_borrar('eliminar', (int) $pedido['id'], 'Eliminar', '¿Eliminar el pedido #' . (int) $pedido['id'] . '? Ya estaba pagado: el stock no cambia.', 'boton-eliminar'),
         } ?>
      </div>
      <?php endforeach; ?>
   </div>

   <?php render_paginador($pag, $filtro === 'todos' ? [] : ['estado' => $filtro]); ?>

</section>

<?php render_cierre('admin_script.js', false); ?>