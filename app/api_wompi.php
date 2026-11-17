<?php
// Webhook de Wompi (configúralo en el panel de Wompi → Desarrolladores → URL de eventos):
//   https://TU-DOMINIO/api_wompi.php
// Wompi avisa aquí cuando una transacción cambia de estado, aunque el cliente haya cerrado la ventana.
// No se confía en el cuerpo: se valida la firma (si hay secreto de eventos) y se vuelve a consultar la
// transacción a la API de Wompi antes de tocar el pedido.
require_once __DIR__ . '/includes/app.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['error' => 'Método no permitido.']));
}

$evento = json_decode(file_get_contents('php://input'), true);
$id = (string) ($evento['data']['transaction']['id'] ?? '');
if (!is_array($evento) || ($evento['event'] ?? '') !== 'transaction.updated' || $id === '') {
    http_response_code(400);
    exit(json_encode(['error' => 'Evento no válido.']));
}
if (!wompi_evento_valido($evento)) {
    app_log('warning', 'Wompi: evento con firma inválida');
    http_response_code(401);
    exit(json_encode(['error' => 'Firma inválida.']));
}

$tx = wompi_consultar($id);
if ($tx === null) {
    http_response_code(502);   // Wompi reintenta si no recibe 200
    exit(json_encode(['error' => 'No se pudo confirmar la transacción.']));
}
[$pedido_id, $estado] = wompi_aplicar($conn, $tx);
app_log('info', "webhook Wompi: transacción $id → $estado (pedido " . ($pedido_id ?? 'desconocido') . ')');
echo json_encode(['ok' => true]);