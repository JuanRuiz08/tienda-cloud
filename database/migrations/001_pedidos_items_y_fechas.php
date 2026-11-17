<?php
/**
 * 001: los pedidos pasan de guardar los productos como texto a tener ítems reales, con fecha real;
 * el carrito identifica el producto por id (no por nombre) y los nombres se limpian.
 */
return function (mysqli $db): void {
    $tabla = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci';

    // Nombres sin espacios sobrantes (el carrito y los pedidos los comparaban con ellos).
    $db->query('UPDATE products SET name = TRIM(name)');
    $db->query('UPDATE cart SET name = TRIM(name)');

    // Cuenta única por correo (el procedimiento registerUser ya lo valida; esto lo garantiza en la base).
    if (!indice_existe($db, 'users', 'uq_users_email')) {
        $db->query('ALTER TABLE users ADD UNIQUE KEY uq_users_email (email)');
    }

    // Pedidos: fecha real y total en dólares cobrado por PayPal.
    if (!columna_existe($db, 'orders', 'created_at')) {
        $db->query('ALTER TABLE orders ADD COLUMN created_at DATETIME NULL AFTER placed_on');
    }
    if (!columna_existe($db, 'orders', 'usd_total')) {
        $db->query('ALTER TABLE orders ADD COLUMN usd_total DECIMAL(10,2) NULL');
    }
    $db->query("UPDATE orders SET created_at = STR_TO_DATE(placed_on, '%d-%b-%Y') WHERE created_at IS NULL");
    $db->query('UPDATE orders SET created_at = NOW() WHERE created_at IS NULL');
    if (!indice_existe($db, 'orders', 'idx_orders_created_at')) {
        $db->query('ALTER TABLE orders ADD KEY idx_orders_created_at (created_at)');
    }

    // Ítems de cada pedido.
    $db->query("CREATE TABLE IF NOT EXISTS order_items (
        id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        product_id INT NULL,
        name VARCHAR(100) NOT NULL,
        price INT NOT NULL,
        quantity INT NOT NULL,
        KEY idx_items_order (order_id),
        KEY idx_items_product (product_id),
        CONSTRAINT fk_items_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE
    ) $tabla");

    // Pedidos anteriores: se reconstruyen los ítems a partir del texto "Nombre (2) , Otro (1) ".
    $productos = [];
    foreach ($db->query('SELECT id, name, price FROM products')->fetch_all(MYSQLI_ASSOC) as $p) {
        $productos[mb_strtolower(trim($p['name']))] = $p;
    }
    $pedidos = $db->query('SELECT o.id, o.total_products FROM orders o
                           WHERE NOT EXISTS (SELECT 1 FROM order_items i WHERE i.order_id = o.id)')->fetch_all(MYSQLI_ASSOC);
    $ins = $db->prepare('INSERT INTO order_items (order_id, product_id, name, price, quantity) VALUES (?, ?, ?, ?, ?)');
    foreach ($pedidos as $pedido) {
        if (!preg_match_all('/(.+?)\s\((\d+)\)\s*(?:,\s*|$)/u', $pedido['total_products'], $m, PREG_SET_ORDER)) { continue; }
        foreach ($m as $fila) {
            $nombre = trim($fila[1]);
            $prod = $productos[mb_strtolower($nombre)] ?? null;
            $id = $prod['id'] ?? null;
            $precio = (int) ($prod['price'] ?? 0);
            $cantidad = (int) $fila[2];
            $oid = (int) $pedido['id'];
            $ins->bind_param('iisii', $oid, $id, $nombre, $precio, $cantidad);
            $ins->execute();
        }
    }
    $ins->close();

    // Carrito por id de producto.
    if (!columna_existe($db, 'cart', 'product_id')) {
        $db->query('ALTER TABLE cart ADD COLUMN product_id INT NULL AFTER user_id');
    }
    $db->query('UPDATE cart c JOIN products p ON p.name = c.name SET c.product_id = p.id WHERE c.product_id IS NULL');
    $db->query('DELETE FROM cart WHERE product_id IS NULL');
    $db->query('DELETE c1 FROM cart c1 JOIN cart c2 ON c1.user_id = c2.user_id AND c1.product_id = c2.product_id AND c1.id > c2.id');
    if (!indice_existe($db, 'cart', 'uq_cart_usuario_producto')) {
        $db->query('ALTER TABLE cart ADD UNIQUE KEY uq_cart_usuario_producto (user_id, product_id)');
    }
};