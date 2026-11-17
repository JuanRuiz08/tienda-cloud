<?php
// De dónde viene el pedido: "web" (cliente en la tienda en línea) o "presencial" (venta registrada por el administrador).
return function (mysqli $db): void {
    if (!columna_existe($db, 'orders', 'origin')) {
        $db->query("ALTER TABLE orders ADD COLUMN origin VARCHAR(20) NOT NULL DEFAULT 'web' AFTER method");
    }
};