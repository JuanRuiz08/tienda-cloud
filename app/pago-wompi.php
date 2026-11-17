<?php
require_once __DIR__ . '/includes/app.php';

$user_id = requerir_usuario();

$pedido = pedido_pendiente($conn, $user_id, (int) ($_GET['pedido'] ?? 0));
if ($pedido === null) {
   redirigir('pedidos.php');
}

$disponible = wompi_configurado();

render_head('Pago en línea');
include 'header.php';
render_encabezado('Pago', 'Pago');
?>

<section class="pago">
   <div class="pago-tarjeta">
      <p class="pago-etiqueta">Pedido #<?= (int) $pedido['id'] ?></p>
      <h1>Completa tu pago</h1>
      <p class="pago-productos"><?= e($pedido['total_products']) ?></p>

      <div class="pago-total">
         <span><?= precio($pedido['total_price']) ?></span>
         <small>Tarjeta, PSE, Nequi y más, en pesos colombianos</small>
      </div>

      <p class="vence-pago"><i class="fas fa-hourglass-half"></i> Tienes <strong><?= max(1, (int) ceil($pedido['segundos_para_pagar'] / 60)) ?> min</strong> para pagar; después el pedido se cancela y los productos vuelven a la tienda.</p>

      <?php if ($disponible): ?>
         <a href="<?= e(wompi_url_pago($pedido)) ?>" class="boton pagar-wompi"><i class="fas fa-lock"></i> Pagar con Wompi</a>
         <?php if (wompi_config()['pruebas']): ?>
         <p class="pago-aviso modo-pruebas">Modo de pruebas: no se cobra dinero real.</p>
         <?php endif; ?>
      <?php else: ?>
         <p class="pago-aviso">El pago en línea no está disponible por ahora. Tu pedido quedó registrado como pendiente.</p>
         <a href="pedidos.php" class="boton">Ver mis pedidos</a>
      <?php endif; ?>

      <p class="pago-nota"><i class="fas fa-shield-halved"></i> Pagas en el sitio seguro de Wompi; esta tienda no ve los datos de tu tarjeta. Puedes pagar más tarde desde <a href="pedidos.php">Mis pedidos</a>.</p>
   </div>
</section>

<?php render_cierre(); ?>