<?php
/**
 * Pagos en línea con Wompi (Bancolombia): tarjetas, PSE, Nequi, etc., cobrados en pesos.
 * Se usa el Checkout web de Wompi: la tienda firma el monto, el cliente paga en el sitio de Wompi y vuelve a
 * pago-resultado.php. Nada de lo que llega del navegador se cree: el pedido solo pasa a «completado» cuando
 * la tienda le pregunta directamente a la API de Wompi y esta confirma APPROVED por el monto exacto del pedido.
 * También lo confirma el webhook (api_wompi.php) por si el cliente cierra la ventana antes de volver.
 *
 * Variables de entorno:
 *   WOMPI_PUBLIC_KEY        llave pública (pub_test_… en pruebas, pub_prod_… en producción)
 *   WOMPI_INTEGRITY_SECRET  secreto de integridad (test_integrity_… / prod_integrity_…): firma el monto
 *   WOMPI_EVENTS_SECRET     secreto de eventos (opcional): si está, el webhook también valida la firma del evento
 *   WOMPI_API_BASE          opcional: sustituye la URL de la API (pruebas con un Wompi simulado)
 *   WOMPI_CHECKOUT_URL      opcional: sustituye la URL del checkout
 *   APP_URL                 opcional: dirección pública de la tienda (por defecto sale de la petición)
 */

function wompi_config(): array
{
    $publica = (string) getenv('WOMPI_PUBLIC_KEY');
    $pruebas = !str_starts_with($publica, 'pub_prod_');
    return [
        'publica' => $publica,
        'integridad' => (string) getenv('WOMPI_INTEGRITY_SECRET'),
        'eventos' => (string) getenv('WOMPI_EVENTS_SECRET'),
        'api' => rtrim(getenv('WOMPI_API_BASE') ?: ($pruebas ? 'https://sandbox.wompi.co/v1' : 'https://production.wompi.co/v1'), '/'),
        'checkout' => getenv('WOMPI_CHECKOUT_URL') ?: 'https://checkout.wompi.co/p/',
        'pruebas' => $pruebas,
    ];
}

function wompi_configurado(): bool
{
    $c = wompi_config();
    return $c['publica'] !== '' && $c['integridad'] !== '';
}

/** Dirección pública de la tienda (sin barra final), para la URL de retorno. */
function url_base(): string
{
    $fija = rtrim((string) getenv('APP_URL'), '/');
    if ($fija !== '') {
        return $fija;
    }
    $host = preg_match('/^[A-Za-z0-9.\-:\[\]]+$/', $_SERVER['HTTP_HOST'] ?? '') ? $_SERVER['HTTP_HOST'] : 'localhost';
    return (https_activo() ? 'https' : 'http') . '://' . $host;
}

/** Referencia única de un intento de pago. El id del pedido va dentro: UCP-<pedido>-<momento>. */
function wompi_referencia(int $pedido_id): string
{
    return "UCP-$pedido_id-" . time();
}

/** Id del pedido que contiene una referencia, o null si no tiene el formato esperado. */
function wompi_pedido_de_referencia(string $referencia): ?int
{
    return preg_match('/^UCP-(\d+)-\d+$/', $referencia, $m) ? (int) $m[1] : null;
}

/** Firma de integridad que exige Wompi: SHA256(referencia + monto en centavos + moneda + secreto). */
function wompi_firma(string $referencia, int $centavos): string
{
    return hash('sha256', $referencia . $centavos . 'COP' . wompi_config()['integridad']);
}

/** Dirección del Checkout de Wompi para pagar un pedido; el monto sale de la base de datos, no del navegador. */
function wompi_url_pago(array $pedido): string
{
    $c = wompi_config();
    $centavos = (int) $pedido['total_price'] * 100;
    $referencia = wompi_referencia((int) $pedido['id']);

    $parametros = [
        'public-key' => $c['publica'],
        'currency' => 'COP',
        'amount-in-cents' => $centavos,
        'reference' => $referencia,
        'signature:integrity' => wompi_firma($referencia, $centavos),
        'redirect-url' => url_base() . '/pago-resultado.php?pedido=' . (int) $pedido['id'],
    ];
    if (filter_var($pedido['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
        $parametros['customer-data:email'] = $pedido['email'];
    }
    if (trim((string) ($pedido['name'] ?? '')) !== '') {
        $parametros['customer-data:full-name'] = mb_substr($pedido['name'], 0, 60);
    }
    return $c['checkout'] . '?' . http_build_query($parametros, '', '&', PHP_QUERY_RFC3986);
}

/** Consulta una transacción a la API de Wompi. Devuelve sus datos o null si no existe / no hay conexión. */
function wompi_consultar(string $transaccion_id): ?array
{
    if (!preg_match('/^[A-Za-z0-9\-_]{5,80}$/', $transaccion_id)) {
        return null;
    }
    $ch = curl_init(wompi_config()['api'] . '/transactions/' . rawurlencode($transaccion_id));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);
    $cuerpo = curl_exec($ch);
    $codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($cuerpo === false || $codigo !== 200) {
        app_log('warning', "Wompi: no se pudo consultar la transacción $transaccion_id (HTTP $codigo)");
        return null;
    }
    return json_decode($cuerpo, true)['data'] ?? null;
}

/**
 * Aplica el resultado de una transacción de Wompi a su pedido. Es idempotente (se puede llamar varias veces,
 * desde la página de retorno y desde el webhook). Devuelve [id del pedido o null, estado de Wompi].
 */
function wompi_aplicar(mysqli $conn, array $tx): array
{
    $estado = (string) ($tx['status'] ?? '');
    $pedido_id = wompi_pedido_de_referencia((string) ($tx['reference'] ?? ''));
    if ($pedido_id === null) {
        return [null, $estado];
    }
    $pedido = consulta($conn, 'SELECT id, total_price, payment_status FROM `orders` WHERE id = ?', 'i', [$pedido_id])[0] ?? null;
    if ($pedido === null) {
        return [null, $estado];
    }

    // Solo se acepta si el monto y la moneda son exactamente los del pedido.
    $coincide = (int) ($tx['amount_in_cents'] ?? -1) === (int) $pedido['total_price'] * 100 && ($tx['currency'] ?? '') === 'COP';
    if ($estado === 'APPROVED' && $coincide && $pedido['payment_status'] === 'pending') {
        consulta($conn, "UPDATE `orders` SET payment_status = 'completed' WHERE id = ? AND payment_status = 'pending'", 'i', [$pedido_id]);
        app_log('info', "pedido $pedido_id pagado con Wompi (transacción {$tx['id']})");
        correo_pedido_diferido($conn, $pedido_id, 'pagado');
    } elseif ($estado === 'APPROVED' && $coincide && $pedido['payment_status'] === 'cancelled') {
        // Pagó justo cuando el pedido ya había vencido (o lo canceló el administrador): si todavía hay stock, se
        // reserva de nuevo y el pedido queda pagado; si no, hay que devolverle el dinero.
        if (reactivar_pedido_pagado($conn, $pedido_id)) {
            app_log('warning', "pedido $pedido_id reactivado: Wompi aprobó el pago (transacción {$tx['id']}) después de cancelarse");
            correo_pedido_diferido($conn, $pedido_id, 'pagado');
            return [$pedido_id, 'APPROVED'];
        }
        app_log('error', "Wompi: se aprobó la transacción {$tx['id']} pero el pedido $pedido_id estaba cancelado y ya no hay stock; hay que devolver el dinero");
        return [$pedido_id, 'CANCELADO_PAGADO'];
    } elseif ($estado === 'APPROVED' && !$coincide) {
        app_log('error', "Wompi: la transacción {$tx['id']} del pedido $pedido_id no coincide en monto o moneda");
        return [$pedido_id, 'MONTO_INVALIDO'];
    }
    return [$pedido_id, $estado];
}

/** Valida la firma de un evento (webhook) con el secreto de eventos: SHA256(valores + timestamp + secreto). */
function wompi_evento_valido(array $evento): bool
{
    $secreto = wompi_config()['eventos'];
    if ($secreto === '') {
        return true;   // sin secreto configurado, la seguridad la da volver a consultar la transacción a Wompi
    }
    $props = $evento['signature']['properties'] ?? [];
    $concatenado = '';
    foreach ($props as $ruta) {
        $dato = $evento['data'] ?? [];
        foreach (explode('.', (string) $ruta) as $clave) {
            $dato = is_array($dato) ? ($dato[$clave] ?? '') : '';
        }
        $concatenado .= is_scalar($dato) ? (string) $dato : '';
    }
    $esperado = hash('sha256', $concatenado . ($evento['timestamp'] ?? '') . $secreto);
    return hash_equals($esperado, strtolower((string) ($evento['signature']['checksum'] ?? '')));
}