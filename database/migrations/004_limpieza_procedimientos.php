<?php
/** 004: los pedidos del cliente y el borrado de pedidos ahora se hacen con consultas con parámetros (con ítems y stock). */
return function (mysqli $db): void {
    foreach (['eliminar_pedido', 'getUserOrders'] as $procedimiento) {
        try {
            $db->query("DROP PROCEDURE IF EXISTS `$procedimiento`");
        } catch (mysqli_sql_exception $e) {
            fwrite(STDERR, "[migrar] aviso: no se pudo borrar $procedimiento ({$e->getMessage()})\n");
        }
    }
};