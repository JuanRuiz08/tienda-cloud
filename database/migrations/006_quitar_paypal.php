<?php
// El pago en línea pasó de PayPal (dólares) a Wompi (pesos): ya no se guarda el monto en USD ni la tasa de cambio.
return function (mysqli $db): void {
    if (columna_existe($db, 'orders', 'usd_total')) {
        $db->query('ALTER TABLE orders DROP COLUMN usd_total');
    }
    $db->query("DELETE FROM config WHERE clave = 'tasa_cop_usd'");
};