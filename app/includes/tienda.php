<?php
/** Lógica de la tienda compartida por las páginas de catálogo: tarjeta de producto y carrito. */

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
            <div class="price">$<?= e($producto['price']) ?> Mil</div>
            <div class="stock<?= $estado_stock ?>"><?= $stock === 0 ? 'Agotado' : "Stock: $stock" ?></div>
         </div>
      </div>
      <div class="acciones">
         <input type="number" min="1" name="product_quantity" value="1" class="qty" aria-label="Cantidad">
         <input type="hidden" name="product_name" value="<?= e($producto['name']) ?>">
         <input type="submit" value="Añadir" name="add_to_cart" class="boton">
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

    $nombre = (string) ($_POST['product_name'] ?? '');
    $cantidad = max(1, (int) ($_POST['product_quantity'] ?? 1));

    $producto = consulta($conn, 'SELECT price, image FROM `products` WHERE name = ? LIMIT 1', 's', [$nombre])[0] ?? null;
    if ($producto === null) {
        return 'El producto no existe.';
    }
    if (consulta($conn, 'CALL checkCartItem(?, ?)', 'is', [$user_id, $nombre])) {
        return '¡Ya añadido al carrito!';
    }

    consulta($conn, 'CALL addCartItem(?, ?, ?, ?, ?)', 'isiis',
        [$user_id, $nombre, $producto['price'], $cantidad, $producto['image']]);
    return '¡Producto añadido al carrito!';
}

/** Pedido del usuario que sigue pendiente de pago, o null (así nadie paga ni ve pedidos ajenos). */
function pedido_pendiente(mysqli $conn, int $user_id, int $pedido_id): ?array
{
    return consulta($conn,
        "SELECT * FROM `orders` WHERE id = ? AND user_id = ? AND payment_status = 'pending'",
        'ii', [$pedido_id, $user_id])[0] ?? null;
}

/** Filas del carrito de un usuario. */
function items_carrito(mysqli $conn, int $user_id): array
{
    return consulta($conn, 'SELECT * FROM `cart` WHERE user_id = ?', 'i', [$user_id]);
}

/** Suma de precio × cantidad de los items del carrito. */
function total_carrito(array $items): int
{
    return array_sum(array_map(fn ($i) => $i['price'] * $i['quantity'], $items));
}
