<?php
require_once __DIR__ . '/includes/app.php';

requerir_admin();

// Texto e icono de cada tipo de acción registrada por auditar().
$tipos = [
   'producto_creado' => ['Producto creado', 'fa-plus'],
   'producto_editado' => ['Producto editado', 'fa-pen'],
   'producto_eliminado' => ['Producto eliminado', 'fa-trash'],
   'pedido_estado' => ['Estado de pedido', 'fa-receipt'],
   'pedido_eliminado' => ['Pedido eliminado', 'fa-trash'],
   'pedido_cancelado' => ['Pedido cancelado', 'fa-ban'],
   'venta_presencial' => ['Venta en tienda', 'fa-cash-register'],
   'usuario_eliminado' => ['Cuenta eliminada', 'fa-user-minus'],
   'clave_cambiada' => ['Contraseña cambiada', 'fa-key'],
   'copia_seguridad' => ['Copia de seguridad', 'fa-database'],
];

$pag = paginar((int) valor($conn, 'SELECT COUNT(*) FROM `auditoria`'), 20);
$registros = consulta($conn, 'SELECT * FROM `auditoria` ORDER BY id DESC LIMIT ? OFFSET ?', 'ii', [$pag['por'], $pag['desde']]);

render_head('Actividad', 'admin_style.css');
include 'admin_header.php';
?>

<section class="actividad">

   <h1 class="title">Actividad del panel</h1>
   <p class="sub-titulo">Quién cambió o borró qué. Solo se guardan acciones del administrador.</p>

   <div class="panel-card" data-vivo="actividad">
      <?php if (!$registros): ?>
      <p class="vacio-panel"><i class="fas fa-clock-rotate-left"></i> Todavía no hay actividad registrada.</p>
      <?php endif; ?>

      <?php foreach ($registros as $r): ?>
      <?php [$texto, $icono] = $tipos[$r['accion']] ?? [$r['accion'], 'fa-circle-info']; ?>
      <div class="registro">
         <span class="icono-registro"><i class="fas <?= e($icono) ?>"></i></span>
         <div class="detalle-registro">
            <strong><?= e($texto) ?></strong>
            <span><?= e($r['detalle']) ?></span>
         </div>
         <div class="quien">
            <strong><?= e($r['admin_nombre']) ?></strong>
            <small><?= e(fecha_corta($r['creado'])) ?> · <?= e(substr($r['creado'], 11, 5)) ?></small>
         </div>
      </div>
      <?php endforeach; ?>
   </div>

   <?php render_paginador($pag); ?>

</section>

<?php render_cierre('admin_script.js', false); ?>