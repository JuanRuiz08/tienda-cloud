<?php
// Vuelve el pago con PayPal (demo): el monto en dólares se congela en el pedido al crear la orden.
return function (mysqli $db): void {
    if (!columna_existe($db, 'orders', 'usd_total')) {
        $db->query('ALTER TABLE orders ADD COLUMN usd_total DECIMAL(10,2) NULL');
    }
};