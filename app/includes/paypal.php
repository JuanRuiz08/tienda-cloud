<?php
/**
 * Cliente mínimo de la API REST de PayPal (Orders v2). Todo el dinero se decide aquí, en el servidor:
 * el total sale de la base de datos y el pedido solo se marca como pagado tras capturar el pago en PayPal.
 *
 * Variables de entorno:
 *   PAYPAL_CLIENT_ID, PAYPAL_CLIENT_SECRET  credenciales de la app en developer.paypal.com
 *   PAYPAL_ENV                              "sandbox" (por defecto) o "live"
 *   PAYPAL_COP_PER_USD                      pesos por dólar (PayPal no cobra en COP); por defecto 4000
 *   PAYPAL_API_BASE                         opcional: sustituye la URL de la API (pruebas con un PayPal simulado)
 */

function paypal_config(): array
{
    $base = getenv('PAYPAL_API_BASE')
        ?: (getenv('PAYPAL_ENV') === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com');

    return [
        'client_id' => (string) getenv('PAYPAL_CLIENT_ID'),
        'secret' => (string) getenv('PAYPAL_CLIENT_SECRET'),
        'base' => rtrim($base, '/'),
        'cop_por_usd' => max(1.0, (float) (getenv('PAYPAL_COP_PER_USD') ?: 4000)),
    ];
}

function paypal_configurado(): bool
{
    $c = paypal_config();
    return $c['client_id'] !== '' && $c['secret'] !== '';
}

/** Convierte el total del pedido (en pesos) a dólares con 2 decimales, mínimo 0.01. */
function total_en_usd(int $total_en_pesos): string
{
    $usd = $total_en_pesos / paypal_config()['cop_por_usd'];
    return number_format(max(0.01, round($usd, 2)), 2, '.', '');
}

/** Llama a la API de PayPal. Devuelve [código HTTP, cuerpo decodificado]; lanza si no hay conexión. */
function paypal_llamar(string $metodo, string $ruta, array $opciones): array
{
    $c = paypal_config();
    $ch = curl_init($c['base'] . $ruta);
    curl_setopt_array($ch, $opciones + [
        CURLOPT_CUSTOMREQUEST => $metodo,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 20,
    ]);
    $cuerpo = curl_exec($ch);
    if ($cuerpo === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException("PayPal no responde: $error");
    }
    $codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$codigo, json_decode($cuerpo, true) ?? []];
}

function paypal_token(): string
{
    $c = paypal_config();
    [$codigo, $r] = paypal_llamar('POST', '/v1/oauth2/token', [
        CURLOPT_USERPWD => $c['client_id'] . ':' . $c['secret'],
        CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);
    if ($codigo !== 200 || empty($r['access_token'])) {
        throw new RuntimeException("PayPal rechazó las credenciales (HTTP $codigo)");
    }
    return $r['access_token'];
}

function paypal_json(string $metodo, string $ruta, array $datos): array
{
    return paypal_llamar($metodo, $ruta, [
        CURLOPT_POSTFIELDS => json_encode($datos ?: new stdClass()),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . paypal_token(),
        ],
    ]);
}

/** Crea la orden en PayPal por el total del pedido y devuelve el id de la orden de PayPal. */
function paypal_crear_orden(int $pedido_id, string $usd): string
{
    [$codigo, $r] = paypal_json('POST', '/v2/checkout/orders', [
        'intent' => 'CAPTURE',
        'purchase_units' => [[
            'reference_id' => "pedido-$pedido_id",
            'custom_id' => (string) $pedido_id,
            'description' => "Pedido #$pedido_id - Papelería U.C.P.",
            'amount' => ['currency_code' => 'USD', 'value' => $usd],
        ]],
    ]);
    if (!in_array($codigo, [200, 201], true) || empty($r['id'])) {
        throw new RuntimeException("No se pudo crear la orden en PayPal (HTTP $codigo)");
    }
    return $r['id'];
}

/**
 * Captura el pago de una orden aprobada. Devuelve true solo si PayPal confirma COMPLETED
 * y por el monto esperado.
 */
function paypal_capturar(string $paypal_id, string $usd_esperado): bool
{
    [$codigo, $r] = paypal_json('POST', '/v2/checkout/orders/' . rawurlencode($paypal_id) . '/capture', []);
    $captura = $r['purchase_units'][0]['payments']['captures'][0] ?? [];

    if (!in_array($codigo, [200, 201], true)) {
        throw new RuntimeException("PayPal no pudo capturar el pago (HTTP $codigo)");
    }
    return ($r['status'] ?? '') === 'COMPLETED'
        && ($captura['status'] ?? '') === 'COMPLETED'
        && ($captura['amount']['currency_code'] ?? '') === 'USD'
        && abs((float) ($captura['amount']['value'] ?? 0) - (float) $usd_esperado) < 0.005;
}
