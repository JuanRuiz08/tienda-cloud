<?php
/**
 * 003: elimina procedimientos almacenados que ya nadie usa (o que apuntaban a tablas inexistentes)
 * y la tabla "message", que nunca se usó. El carrito y los pedidos ahora van con consultas con parámetros.
 */
return function (mysqli $db): void {
    foreach (['actualizar_estado_pago', 'insertOrder', 'eliminar_producto_carrito', 'actualizar_cantidad_carrito',
              'addCartItem', 'checkCartItem', 'actualizar_producto'] as $procedimiento) {
        try {
            $db->query("DROP PROCEDURE IF EXISTS `$procedimiento`");
        } catch (mysqli_sql_exception $e) {
            // Un procedimiento creado por otro usuario (p. ej. root en el entorno local) puede no ser
            // borrable por el usuario de la app. Queda sin usar: no es un error.
            fwrite(STDERR, "[migrar] aviso: no se pudo borrar $procedimiento ({$e->getMessage()})\n");
        }
    }
    $db->query('DROP TABLE IF EXISTS message');
};