<?php
// Página a la que Wompi devuelve al cliente (?pedido=ID&id=TRANSACCION). No se confía en lo que trae la URL:
// se consulta la transacción a la API de Wompi y solo entonces se actualiza el pedido.
require_once __DIR__ . '/includes/app.php';

$user_id = requerir_usuario();
$pedido_id = (int) ($_GET['pedido'] ?? 0);

$pedido = consulta($conn, 'SELECT id, payment_status, total_price FROM `orders` WHERE id = ? AND user_id = ?', 'ii', [$pedido_id, $user_id])[0] ?? null;
if ($pedido === null) {
   redirigir('pedidos.php');
}

$estado = $pedido['payment_status'] === 'completed' ? 'APPROVED' : '';
$tx = wompi_consultar((string) ($_GET['id'] ?? ''));
if ($tx !== null) {
   [$de_pedido, $estado_wompi] = wompi_aplicar($conn, $tx);
   if ($de_pedido === $pedido_id) {
      $estado = $estado_wompi;
   }
}

// [icono, clase, título, mensaje, ¿puede reintentar?]
[$icono, $clase, $titulo, $mensaje, $reintentar] = match ($estado) {
   'APPROVED' => ['fa-circle-check', 'ok', '¡Pago recibido!', 'Tu pedido ya está pagado. Te avisaremos cuando esté listo.', false],
   'PENDING', '' => ['fa-hourglass-half', 'espera', 'Estamos confirmando tu pago', 'Wompi aún no nos confirma el resultado (con PSE puede tardar unos minutos). Esta página no se actualiza sola: revisa «Mis pedidos» en un rato.', true],
   'CANCELADO_PAGADO' => ['fa-triangle-exclamation', 'error', 'Este pedido ya estaba cancelado', 'Recibimos tu pago, pero el pedido había sido cancelado. Contáctanos y lo resolvemos o te devolvemos el dinero.', false],
   'MONTO_INVALIDO' => ['fa-triangle-exclamation', 'error', 'No pudimos validar el pago', 'El monto no coincide con el pedido. No se marcó como pagado; contáctanos si te cobraron.', false],
   default => ['fa-circle-xmark', 'error', 'El pago no se aprobó', 'No se hizo ningún cobro. Puedes intentarlo de nuevo con el mismo u otro medio de pago.', true],
};

render_head('Resultado del pago');
include 'header.php';
render_encabezado('Pago', 'Resultado');
?>

<section class="pago">
   <div class="pago-tarjeta resultado-<?= e($clase) ?>">
      <i class="fas <?= e($icono) ?> resultado-icono"></i>
      <p class="pago-etiqueta">Pedido #<?= $pedido_id ?></p>
      <h1><?= e($titulo) ?></h1>
      <p class="pago-productos"><?= e($mensaje) ?></p>
      <div class="resultado-acciones">
         <?php if ($reintentar && $pedido['payment_status'] === 'pending'): ?>
         <a href="pago-wompi.php?pedido=<?= $pedido_id ?>" class="boton">Intentar de nuevo</a>
         <?php endif; ?>
         <a href="pedidos.php" class="boton-opcion">Ver mis pedidos</a>
      </div>
   </div>
</section>

<?php render_cierre(); ?>