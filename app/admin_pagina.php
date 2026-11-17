<?php
require_once __DIR__ . '/includes/app.php';

requerir_admin();

/** Fecha de un pedido, o null si no se puede leer. */
function fecha_pedido(string $texto): ?DateTimeImmutable
{
   // created_at es una fecha real ("2026-11-17 15:30:00"); los pedidos muy antiguos solo tienen el texto "17-Nov-2026".
   $d = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $texto) ?: DateTimeImmutable::createFromFormat('!d-M-Y', $texto);
   return $d ?: null;
}

/** "hoy", "ayer" o "hace N días", según la fecha de un pedido. */
function hace_cuanto(string $texto, DateTimeImmutable $hoy): string
{
   $fecha = fecha_pedido($texto);
   if ($fecha === null) {
      return $texto;
   }
   $dias = (int) $fecha->diff($hoy)->format('%r%a');
   return match (true) {
      $dias <= 0 => 'hoy',
      $dias === 1 => 'ayer',
      default => "hace $dias días",
   };
}

// Acción rápida: marcar como completado un pedido desde el panel.
if (isset($_POST['completar'])) {
   consulta($conn, "UPDATE `orders` SET payment_status = 'completed' WHERE id = ?", 'i', [(int) $_POST['order_id']]);
   auditar($conn, 'pedido_estado', 'Pedido #' . (int) $_POST['order_id'] . ' → Completado (desde el panel)');
   correo_pedido_diferido($conn, (int) $_POST['order_id'], 'completado');
   $message[] = '¡Pedido #' . (int) $_POST['order_id'] . ' marcado como completado!';
}

$hoy = new DateTimeImmutable('today');

// ---------- Resumen (tarjetas de arriba): [valor, etiqueta] ----------
$total_pendientes = (int) valor($conn, 'SELECT COALESCE(SUM(total_price), 0) FROM `orders` WHERE payment_status = ?', 's', ['pending']);
$total_completados = (int) valor($conn, 'SELECT COALESCE(SUM(total_price), 0) FROM `orders` WHERE payment_status = ?', 's', ['completed']);

$tarjetas = [
   [precio($total_pendientes), 'total pendientes'],
   [precio($total_completados), 'pagos completados'],
   [valor($conn, 'SELECT COUNT(*) FROM `orders`'), 'Pedidos realizados'],
   [valor($conn, 'SELECT COUNT(*) FROM `products`'), 'productos añadidos'],
   [valor($conn, 'SELECT COUNT(*) FROM `users` WHERE user_type = ?', 's', ['user']), 'usuarios normales'],
   [valor($conn, 'SELECT COUNT(*) FROM `users` WHERE user_type = ?', 's', ['admin']), 'Administradores'],
   [valor($conn, 'SELECT COUNT(*) FROM `users`'), 'total cuentas'],
];

// ---------- Requiere atención ----------
$n_pendientes = (int) valor($conn, "SELECT COUNT(*) FROM `orders` WHERE payment_status = 'pending'");
$lista_pendientes = consulta($conn, "SELECT id, name, total_price, created_at, placed_on FROM `orders` WHERE payment_status = 'pending' ORDER BY id ASC LIMIT 4");
$stock_bajo = consulta($conn, 'SELECT id, name, stock FROM `products` WHERE stock <= 3 ORDER BY stock ASC, name ASC LIMIT 6');

// ---------- Ventas: lo cobrado (pedidos completados) en los últimos 7 días y en el mes ----------
$completados = consulta($conn, "SELECT created_at, placed_on, total_price FROM `orders` WHERE payment_status = 'completed'");
$por_dia = [];
$mes = ['total' => 0, 'pedidos' => 0];
foreach ($completados as $pedido) {
   $fecha = fecha_pedido($pedido['created_at'] ?: $pedido['placed_on']);
   if ($fecha === null) {
      continue;
   }
   $por_dia[$fecha->format('Y-m-d')] = ($por_dia[$fecha->format('Y-m-d')] ?? 0) + (int) $pedido['total_price'];
   if ($fecha->format('Y-m') === $hoy->format('Y-m')) {
      $mes['total'] += (int) $pedido['total_price'];
      $mes['pedidos']++;
   }
}
$nombres_dia = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
$semana = [];
for ($i = 6; $i >= 0; $i--) {
   $dia = $hoy->modify("-$i days");
   $semana[] = [
      'etiqueta' => $nombres_dia[(int) $dia->format('w')] . ' ' . $dia->format('j'),
      'total' => $por_dia[$dia->format('Y-m-d')] ?? 0,
      'hoy' => $i === 0,
   ];
}
$maximo = max(array_column($semana, 'total'));
$total_semana = array_sum(array_column($semana, 'total'));
$ticket = $mes['pedidos'] ? intdiv($mes['total'], $mes['pedidos']) : 0;

// ---------- Últimos pedidos ----------
$recientes = consulta($conn, 'SELECT * FROM `orders` ORDER BY id DESC LIMIT 5');
$estados = ['pending' => 'Pendiente', 'completed' => 'Completado'];

render_head('panel de administración', 'admin_style.css');
include 'admin_header.php';
?>

<section class="dashboard">

   <h1 class="title">Panel</h1>

   <div class="rapidas" data-vivo="rapidas">
      <a href="admin_productos.php#nuevo-producto" class="boton"><i class="fas fa-plus"></i> Añadir producto</a>
      <a href="admin_pedidos.php?estado=pending" class="boton-opcion"><i class="fas fa-clock"></i> Pedidos pendientes<?= $n_pendientes ? " ($n_pendientes)" : '' ?></a>
      <a href="inicio.php" target="_blank" rel="noopener" class="boton-opcion"><i class="fas fa-store"></i> Ver tienda</a>
   </div>

   <div class="caja-container" data-vivo="resumen">
      <?php foreach ($tarjetas as [$cifra, $etiqueta]): ?>
      <div class="caja">
         <h3><?= e($cifra) ?></h3>
         <p><?= e($etiqueta) ?></p>
      </div>
      <?php endforeach; ?>
   </div>

   <div class="panel-grid" data-vivo="panel">

      <!-- Ventas de los últimos 7 días -->
      <div class="panel-card">
         <h2>Ventas de los últimos 7 días</h2>
         <p class="sub">Solo cuenta lo cobrado: pedidos en estado completado.</p>

         <div class="metricas">
            <div class="metrica"><span>Últimos 7 días</span><strong><?= precio($total_semana) ?></strong></div>
            <div class="metrica"><span>Cobrado este mes</span><strong><?= precio($mes['total']) ?></strong></div>
            <div class="metrica"><span>Ticket promedio</span><strong><?= precio($ticket) ?></strong></div>
         </div>

         <?php if ($maximo > 0): ?>
         <div class="grafica" role="img" aria-label="Ventas por día de los últimos 7 días">
            <?php foreach ($semana as $i => $dia): ?>
            <div class="dia<?= $dia['hoy'] ? ' hoy' : '' ?>" style="--i: <?= $i ?>">
               <span class="valor"><?= $dia['total'] ? precio($dia['total']) : '' ?></span>
               <div class="pista"><div class="barra" style="height: <?= $dia['total'] ? max(4, round($dia['total'] / $maximo * 100)) : 0 ?>%"></div></div>
               <span class="etq"><?= e($dia['etiqueta']) ?></span>
            </div>
            <?php endforeach; ?>
         </div>
         <?php else: ?>
         <p class="vacio-panel"><i class="fas fa-chart-simple"></i> Aún no hay ventas cobradas en los últimos 7 días.</p>
         <?php endif; ?>
      </div>

      <!-- Requiere atención -->
      <div class="panel-card">
         <h2>Requiere tu atención</h2>
         <p class="sub">Lo que conviene revisar hoy.</p>

         <h3 class="subtitulo">Pedidos pendientes <span class="cuenta"><?= $n_pendientes ?></span></h3>
         <?php if ($lista_pendientes): ?>
         <ul class="alertas">
            <?php foreach ($lista_pendientes as $p): ?>
            <li class="alerta pend">
               <i class="fas fa-clock"></i>
               <div><strong>#<?= (int) $p['id'] ?> · <?= e($p['name']) ?></strong><small><?= precio($p['total_price']) ?> · <?= e(hace_cuanto($p['created_at'] ?: $p['placed_on'], $hoy)) ?></small></div>
               <a href="admin_pedidos.php?estado=pending#pedido-<?= (int) $p['id'] ?>" class="mini">Ver</a>
            </li>
            <?php endforeach; ?>
         </ul>
         <?php if ($n_pendientes > count($lista_pendientes)): ?>
         <a href="admin_pedidos.php?estado=pending" class="ver-mas">Ver los <?= $n_pendientes ?> pendientes <i class="fas fa-arrow-right"></i></a>
         <?php endif; ?>
         <?php else: ?>
         <p class="todo-bien"><i class="fas fa-circle-check"></i> No hay pedidos pendientes.</p>
         <?php endif; ?>

         <h3 class="subtitulo">Stock bajo <span class="cuenta"><?= count($stock_bajo) ?></span></h3>
         <?php if ($stock_bajo): ?>
         <ul class="alertas">
            <?php foreach ($stock_bajo as $s): ?>
            <li class="alerta <?= (int) $s['stock'] === 0 ? 'agotado' : 'bajo' ?>">
               <i class="fas fa-box-open"></i>
               <div><strong><?= e(trim($s['name'])) ?></strong><small><?= (int) $s['stock'] === 0 ? 'Agotado' : 'Quedan ' . (int) $s['stock'] ?></small></div>
               <a href="admin_productos.php?update=<?= (int) $s['id'] ?>" class="mini">Reponer</a>
            </li>
            <?php endforeach; ?>
         </ul>
         <?php else: ?>
         <p class="todo-bien"><i class="fas fa-circle-check"></i> Todos los productos tienen stock suficiente.</p>
         <?php endif; ?>
      </div>

   </div>

   <!-- Últimos pedidos -->
   <div class="panel-card recientes" data-vivo="recientes">
      <div class="cabecera-card">
         <div>
            <h2>Últimos pedidos</h2>
            <p class="sub">Los 5 más recientes.</p>
         </div>
         <a href="admin_pedidos.php" class="ver-mas">Ver todos <i class="fas fa-arrow-right"></i></a>
      </div>

      <?php if (!$recientes): ?>
      <p class="vacio-panel"><i class="fas fa-receipt"></i> Todavía no hay pedidos.</p>
      <?php endif; ?>

      <?php foreach ($recientes as $p): ?>
      <div class="fila-pedido">
         <strong class="num">#<?= (int) $p['id'] ?></strong>
         <div class="cliente"><span><?= e($p['name']) ?></span><small><?= e(hace_cuanto($p['created_at'] ?: $p['placed_on'], $hoy)) ?></small></div>
         <span class="metodo"><?= e($p['method']) ?></span>
         <strong class="total"><?= precio($p['total_price']) ?></strong>
         <span class="estado estado-<?= e($p['payment_status']) ?>"><?= e($estados[$p['payment_status']] ?? $p['payment_status']) ?></span>
         <div class="accion">
            <?php if ($p['payment_status'] === 'pending'): ?>
            <form action="" method="post">
               <?= csrf_campo() ?>
               <input type="hidden" name="order_id" value="<?= (int) $p['id'] ?>">
               <button type="submit" name="completar" class="mini">Marcar completado</button>
            </form>
            <?php endif; ?>
         </div>
      </div>
      <?php endforeach; ?>
   </div>

</section>

<?php render_cierre('admin_script.js', false); ?>
