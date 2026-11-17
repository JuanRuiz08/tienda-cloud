<?php
/**
 * Lógica de la tienda compartida por las páginas: tarjeta de producto, carrito y pedidos.
 * El carrito y los pedidos identifican los productos por id (no por nombre) y todo lo que toca el dinero
 * o el stock se decide aquí, en el servidor, nunca con datos del formulario.
 */

/** Imprime la tarjeta de un producto con su formulario "añadir al carrito". */
function tarjeta_producto(array $producto): void
{
    $stock = (int) $producto['stock'];
    $estado_stock = $stock === 0 ? ' agotado' : ($stock <= 3 ? ' bajo' : '');
    ?>
   <form action="" method="post" class="caja" id="producto-<?= (int) $producto['id'] ?>">
      <div class="media"><img class="image" src="uploaded_img/<?= e($producto['image']) ?>" alt="<?= e($producto['name']) ?>" loading="lazy"></div>
      <div class="info">
         <div class="name"><?= e($producto['name']) ?></div>
         <div class="meta">
            <div class="price"><?= precio($producto['price']) ?></div>
            <div class="stock<?= $estado_stock ?>"><?= $stock === 0 ? 'Agotado' : "Stock: $stock" ?></div>
         </div>
      </div>
      <div class="acciones">
         <?= csrf_campo() ?>
         <input type="number" min="1" max="<?= max($stock, 1) ?>" name="product_quantity" value="1" class="qty" aria-label="Cantidad"<?= $stock === 0 ? ' disabled' : '' ?>>
         <input type="hidden" name="product_id" value="<?= (int) $producto['id'] ?>">
         <input type="submit" value="<?= $stock === 0 ? 'Agotado' : 'Añadir' ?>" name="add_to_cart" class="boton"<?= $stock === 0 ? ' disabled' : '' ?>>
      </div>
   </form>
<?php
}

/** Imprime una lista de tarjetas, o $vacio si no hay productos. */
function render_productos(array $productos, string $vacio): void
{
    if (!$productos) {
        echo '<p class="vacio">' . e($vacio) . '</p>';
        return;
    }
    foreach ($productos as $producto) {
        tarjeta_producto($producto);
    }
}

/**
 * Procesa el formulario "añadir al carrito" y devuelve el aviso a mostrar (o null si no se envió).
 * El precio y la imagen se leen de la base de datos, no del formulario, para que no se puedan alterar.
 */
function procesar_agregar_al_carrito(mysqli $conn, ?int $user_id): ?string
{
    if (!isset($_POST['add_to_cart'])) {
        return null;
    }
    if ($user_id === null) {
        redirigir('login.php?requiere=1'); // los invitados ven el catálogo, pero comprar exige cuenta
    }

    $producto_id = (int) ($_POST['product_id'] ?? 0);
    $cantidad = max(1, (int) ($_POST['product_quantity'] ?? 1));

    $producto = consulta($conn, 'SELECT id, name, price, image, stock FROM `products` WHERE id = ?', 'i', [$producto_id])[0] ?? null;
    if ($producto === null) {
        return 'El producto no existe.';
    }
    $nombre = trim($producto['name']);
    $stock = (int) $producto['stock'];
    if ($stock <= 0) {
        return "$nombre está agotado.";
    }
    if (valor($conn, 'SELECT COUNT(*) FROM `cart` WHERE user_id = ? AND product_id = ?', 'ii', [$user_id, $producto_id])) {
        return '¡Ya añadido al carrito!';
    }
    if ($cantidad > $stock) {
        return "Solo quedan $stock unidades de $nombre.";
    }

    consulta($conn, 'INSERT INTO `cart` (user_id, product_id, name, price, quantity, image) VALUES (?, ?, ?, ?, ?, ?)', 'iisiis',
        [$user_id, $producto_id, $nombre, $producto['price'], $cantidad, $producto['image']]);
    return '¡Producto añadido al carrito!';
}

/** Pedido del usuario que sigue pendiente de pago, o null (así nadie paga ni ve pedidos ajenos). */
function pedido_pendiente(mysqli $conn, int $user_id, int $pedido_id): ?array
{
    return consulta($conn,
        "SELECT * FROM `orders` WHERE id = ? AND user_id = ? AND payment_status = 'pending'",
        'ii', [$pedido_id, $user_id])[0] ?? null;
}

/** Filas del carrito de un usuario, con el stock actual de cada producto (`stock`). */
function items_carrito(mysqli $conn, int $user_id): array
{
    return consulta($conn,
        'SELECT c.*, COALESCE(p.stock, 0) AS stock
         FROM `cart` c LEFT JOIN `products` p ON p.id = c.product_id
         WHERE c.user_id = ?
         ORDER BY c.id',
        'i', [$user_id]);
}

/** Items del carrito cuya cantidad supera el stock disponible (el stock pudo bajar tras añadirlos). */
function items_sin_stock(array $items): array
{
    return array_values(array_filter($items, fn ($i) => (int) $i['quantity'] > (int) $i['stock']));
}

/** Suma de precio × cantidad de los items del carrito. */
function total_carrito(array $items): int
{
    return array_sum(array_map(fn ($i) => $i['price'] * $i['quantity'], $items));
}

/**
 * Descuenta del stock lo que hay en el carrito. Debe llamarse dentro de una transacción: devuelve un
 * aviso si algún producto no alcanza (y el llamador hace rollback), o null si todo se descontó.
 * El UPDATE condicional es atómico: dos compras simultáneas no pueden dejar el stock en negativo.
 */
function reservar_stock(mysqli $conn, array $items): ?string
{
    $st = $conn->prepare('UPDATE `products` SET stock = stock - ? WHERE id = ? AND stock >= ?');
    foreach ($items as $item) {
        $cantidad = (int) $item['quantity'];
        $producto = (int) $item['product_id'];
        $st->bind_param('iii', $cantidad, $producto, $cantidad);
        $st->execute();
        if ($st->affected_rows < 1) {
            $st->close();
            $quedan = (int) valor($conn, 'SELECT stock FROM `products` WHERE id = ?', 'i', [$producto]);
            $nombre = trim((string) $item['name']);
            return $quedan > 0
                ? "Solo quedan $quedan unidades de $nombre. Ajusta la cantidad en tu carrito."
                : "$nombre se agotó. Quítalo del carrito para continuar.";
        }
    }
    $st->close();
    return null;
}

/**
 * Crea un pedido con lo que hay en el carrito: descuenta el stock, guarda el pedido y sus ítems y vacía el
 * carrito, todo en una sola transacción (o se hace todo o no se hace nada).
 * Devuelve [id del pedido, null] o [null, aviso con el motivo].
 *
 * @param array $datos name, number, email, direccion
 */
function crear_pedido(mysqli $conn, int $user_id, array $items, array $datos, string $metodo): array
{
    $total = total_carrito($items);
    $resumen = implode(', ', array_map(fn ($i) => trim($i['name']) . ' (' . $i['quantity'] . ') ', $items));

    $conn->begin_transaction();
    try {
        $aviso = reservar_stock($conn, $items);
        if ($aviso !== null) {
            $conn->rollback();
            return [null, $aviso];
        }

        consulta($conn,
            'INSERT INTO `orders` (user_id, name, number, email, method, address, total_products, total_price, placed_on, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
            'issssssis',
            [$user_id, $datos['name'], $datos['number'], $datos['email'], $metodo, $datos['direccion'], $resumen, $total, date('d-M-Y')]);
        $pedido_id = (int) valor($conn, 'SELECT LAST_INSERT_ID()');

        foreach ($items as $item) {
            consulta($conn, 'INSERT INTO `order_items` (order_id, product_id, name, price, quantity) VALUES (?, ?, ?, ?, ?)', 'iisii',
                [$pedido_id, (int) $item['product_id'], trim($item['name']), (int) $item['price'], (int) $item['quantity']]);
        }
        consulta($conn, 'DELETE FROM `cart` WHERE user_id = ?', 'i', [$user_id]);
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }

    app_log('info', "pedido $pedido_id creado user_id=$user_id total=$total método=$metodo");
    return [$pedido_id, null];
}

/** Devuelve al stock lo que reservó un pedido (a partir de sus ítems). */
function devolver_stock(mysqli $conn, int $pedido_id): void
{
    consulta($conn,
        'UPDATE `products` p JOIN `order_items` i ON i.product_id = p.id
         SET p.stock = p.stock + i.quantity
         WHERE i.order_id = ?',
        'i', [$pedido_id]);
}

/**
 * Borra un pedido. Si todavía estaba pendiente se trata como una cancelación y el stock vuelve a la tienda;
 * si ya estaba pagado, el producto salió de verdad y el stock no se toca. Devuelve el texto del aviso.
 */
function eliminar_pedido(mysqli $conn, int $pedido_id): string
{
    $pedido = consulta($conn, 'SELECT id, payment_status FROM `orders` WHERE id = ?', 'i', [$pedido_id])[0] ?? null;
    if ($pedido === null) {
        return 'El pedido no existe.';
    }

    $conn->begin_transaction();
    try {
        $devuelve = $pedido['payment_status'] === 'pending';
        if ($devuelve) {
            devolver_stock($conn, $pedido_id);
        }
        consulta($conn, 'DELETE FROM `orders` WHERE id = ?', 'i', [$pedido_id]);   // los ítems se borran en cascada
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
    return $devuelve
        ? "Pedido #$pedido_id cancelado: el stock volvió a la tienda."
        : "Pedido #$pedido_id eliminado (estaba pagado, el stock no cambia).";
}
