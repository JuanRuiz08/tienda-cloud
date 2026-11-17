<?php
require_once __DIR__ . '/includes/app.php';

$admin_id = requerir_admin();

$metodos = ['efectivo' => 'Efectivo', 'datafono' => 'Datáfono (tarjeta)', 'transferencia' => 'Transferencia / Nequi'];
$cantidades = [];   // lo que el admin ya había escrito, para no perderlo si hay un error

if (isset($_POST['registrar_venta'])) {
   $cantidades = array_filter(array_map('intval', (array) ($_POST['cantidad'] ?? [])), fn ($n) => $n > 0);
   $metodo = isset($metodos[$_POST['metodo'] ?? '']) ? $_POST['metodo'] : 'efectivo';
   $cliente = mb_substr(trim((string) ($_POST['cliente'] ?? '')), 0, 100) ?: 'Cliente en tienda';

   // Precio y nombre salen de la base de datos, no del formulario.
   $items = [];
   if ($cantidades) {
      $marcas = implode(',', array_fill(0, count($cantidades), '?'));
      foreach (consulta($conn, "SELECT id, name, price FROM `products` WHERE id IN ($marcas)", str_repeat('i', count($cantidades)), array_keys($cantidades)) as $p) {
         $items[] = ['product_id' => (int) $p['id'], 'name' => $p['name'], 'price' => (int) $p['price'], 'quantity' => $cantidades[$p['id']]];
      }
   }

   if (!$items) {
      $message[] = 'Escoge al menos un producto para registrar la venta.';
   } else {
      [$pedido_id, $aviso] = crear_pedido($conn, $admin_id, $items, [
         'name' => $cliente,
         'number' => '-',
         'email' => '',
         'direccion' => 'Venta en tienda (presencial)',
      ], $metodo, 'presencial');

      if ($pedido_id === null) {
         $message[] = $aviso;   // no alcanzó el stock: no se registró nada
      } else {
         $total = total_carrito($items);
         auditar($conn, 'venta_presencial', "Venta #$pedido_id por " . precio($total) . " ({$metodos[$metodo]})");
         $message[] = "¡Venta #$pedido_id registrada por " . precio($total) . '! El stock ya se descontó.';
         $cantidades = [];
      }
   }
}

$productos = consulta($conn, 'SELECT id, name, price, stock, image FROM `products` ORDER BY name');
$ultimas = consulta($conn, "SELECT id, name, total_price, method, created_at FROM `orders` WHERE origin = 'presencial' ORDER BY id DESC LIMIT 6");

render_head('Venta en tienda', 'admin_style.css');
include 'admin_header.php';
?>

<section class="venta">

   <h1 class="title">Venta en tienda</h1>

   <form action="" method="post" class="venta-grid" id="form-venta">
      <?= csrf_campo() ?>

      <div class="panel-card venta-lista">
         <h2>Productos</h2>
         <p class="sub">Escribe la cantidad que se llevó el cliente. Lo que está agotado no se puede vender.</p>

         <input type="search" id="venta-buscar" placeholder="Buscar producto…" aria-label="Buscar producto" autocomplete="off">

         <ul class="venta-productos">
            <?php foreach ($productos as $p): ?>
            <li class="venta-fila<?= (int) $p['stock'] === 0 ? ' agotado' : '' ?>" data-nombre="<?= e(mb_strtolower(trim($p['name']))) ?>">
               <img src="uploaded_img/<?= e($p['image']) ?>" alt="" loading="lazy">
               <div class="venta-dato">
                  <strong><?= e(trim($p['name'])) ?></strong>
                  <small><?= precio($p['price']) ?> · <?= (int) $p['stock'] === 0 ? 'Agotado' : 'Stock: ' . (int) $p['stock'] ?></small>
               </div>
               <input type="number" min="0" max="<?= (int) $p['stock'] ?>" step="1" inputmode="numeric"
                      name="cantidad[<?= (int) $p['id'] ?>]" value="<?= (int) ($cantidades[$p['id']] ?? 0) ?>"
                      data-precio="<?= (int) $p['price'] ?>" aria-label="Cantidad de <?= e(trim($p['name'])) ?>"
                      <?= (int) $p['stock'] === 0 ? 'disabled' : '' ?>>
            </li>
            <?php endforeach; ?>
         </ul>
      </div>

      <aside class="panel-card venta-resumen">
         <h2>Resumen</h2>
         <p class="venta-total"><span id="venta-total">$0</span><small id="venta-unidades">0 productos</small></p>

         <label class="campo"><span>Cliente (opcional)</span>
            <input type="text" name="cliente" maxlength="100" placeholder="Cliente en tienda" value="<?= e($_POST['cliente'] ?? '') ?>">
         </label>
         <label class="campo"><span>Forma de pago</span>
            <select name="metodo">
               <?php foreach ($metodos as $clave => $texto): ?>
               <option value="<?= $clave ?>"<?= ($_POST['metodo'] ?? 'efectivo') === $clave ? ' selected' : '' ?>><?= $texto ?></option>
               <?php endforeach; ?>
            </select>
         </label>

         <button type="submit" name="registrar_venta" class="boton" id="venta-registrar" disabled><i class="fas fa-cash-register"></i> Registrar venta</button>
         <p class="venta-nota">La venta queda como <strong>completada</strong> y el stock se descuenta al instante.</p>
      </aside>
   </form>

   <div class="panel-card venta-ultimas">
      <h2>Últimas ventas en tienda</h2>
      <?php if (!$ultimas): ?>
      <p class="vacio-panel"><i class="fas fa-cash-register"></i> Aún no hay ventas registradas aquí.</p>
      <?php endif; ?>
      <?php foreach ($ultimas as $u): ?>
      <div class="fila-pedido">
         <strong class="num">#<?= (int) $u['id'] ?></strong>
         <div class="cliente"><span><?= e($u['name']) ?></span><small><?= e(fecha_corta($u['created_at'])) ?></small></div>
         <span class="metodo"><?= e($metodos[$u['method']] ?? $u['method']) ?></span>
         <strong class="total"><?= precio($u['total_price']) ?></strong>
      </div>
      <?php endforeach; ?>
   </div>

</section>

<?php render_cierre('admin_script.js', false, ['venta.js']); ?>