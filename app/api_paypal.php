<?php
// Endpoints del botón de PayPal (los llama js/paypal.js):
//   POST ?accion=crear&pedido=ID     crea la orden en PayPal con el total del pedido (leído de la base)
//   POST ?accion=capturar&pedido=ID  captura el pago y, solo si PayPal lo confirma, marca el pedido como pagado
require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/paypal.php';

header('Content-Type: application/json; charset=utf-8');

function responder(int $codigo, array $datos): never
{
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(405, ['error' => 'Método no permitido.']);
}
$user_id = usuario_actual();
if ($user_id === null) {
    responder(401, ['error' => 'Inicia sesión para pagar.']);
}
if (!paypal_configurado()) {
    responder(503, ['error' => 'El pago en línea no está disponible por ahora.']);
}

$pedido = pedido_pendiente($conn, $user_id, (int) ($_GET['pedido'] ?? 0));
if ($pedido === null) {
    responder(404, ['error' => 'No hay un pedido pendiente de pago (puede haber vencido).']);
}
$id = (int) $pedido['id'];
$total = (int) $pedido['total_price'];

try {
    switch ($_GET['accion'] ?? '') {
        case 'crear':
            // La tasa se congela aquí: el monto en dólares queda guardado en el pedido y es el que se exige al capturar.
            $tasa = tasa_cop_usd($conn);
            $usd = total_en_usd($total, $tasa);
            $paypal_id = paypal_crear_orden($id, $usd);
            consulta($conn, 'UPDATE `orders` SET usd_total = ? WHERE id = ?', 'si', [$usd, $id]);
            $_SESSION['paypal_orden'][$id] = $paypal_id;   // se compara al capturar
            app_log('info', "orden PayPal $paypal_id creada para el pedido $id (USD $usd, tasa $tasa)");
            responder(200, ['id' => $paypal_id]);

        case 'capturar':
            $enviado = (string) (json_decode(file_get_contents('php://input'), true)['id'] ?? '');
            if ($enviado === '' || !hash_equals($_SESSION['paypal_orden'][$id] ?? '', $enviado)) {
                responder(400, ['error' => 'La orden de PayPal no corresponde a este pedido.']);
            }
            $usd = (string) ($pedido['usd_total'] ?? '');
            if ($usd === '' || (float) $usd <= 0) {
                responder(400, ['error' => 'Primero inicia el pago desde el botón de PayPal.']);
            }
            if (!paypal_capturar($enviado, $usd)) {
                app_log('warning', "pago no confirmado para el pedido $id (orden $enviado)");
                responder(402, ['error' => 'PayPal no confirmó el pago.']);
            }
            consulta($conn, "UPDATE `orders` SET payment_status = 'completed' WHERE id = ? AND user_id = ?", 'ii', [$id, $user_id]);
            unset($_SESSION['paypal_orden'][$id]);
            correo_pedido_diferido($conn, $id, 'pagado');
            app_log('info', "pedido $id pagado con PayPal (orden $enviado, USD $usd)");
            responder(200, ['ok' => true]);

        default:
            responder(400, ['error' => 'Acción desconocida.']);
    }
} catch (Throwable $e) {
    app_log('error', 'PayPal: ' . $e->getMessage());
    responder(502, ['error' => 'No se pudo completar el pago con PayPal. Intenta de nuevo.']);
}