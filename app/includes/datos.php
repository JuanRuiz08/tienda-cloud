<?php
/** Acceso a datos: todas las consultas pasan por aquí con parámetros (sin armar SQL con variables). */

/**
 * Ejecuta una consulta preparada o un CALL y devuelve las filas (vacío si no devuelve resultado).
 * $tipos es el formato de bind_param ('i', 's', ...) y $params los valores en el mismo orden.
 */
function consulta(mysqli $conn, string $sql, string $tipos = '', array $params = []): array
{
    $st = $conn->prepare($sql);
    if ($tipos !== '') {
        $st->bind_param($tipos, ...$params);
    }
    $st->execute();
    $resultado = $st->get_result();
    $filas = $resultado ? $resultado->fetch_all(MYSQLI_ASSOC) : [];
    $st->close();
    // Un CALL deja resultados pendientes; hay que vaciarlos antes de la siguiente consulta.
    while ($conn->more_results()) {
        $conn->next_result();
    }
    return $filas;
}

/** Devuelve la primera columna de la primera fila (por ejemplo, un COUNT o un SUM), o null. */
function valor(mysqli $conn, string $sql, string $tipos = '', array $params = []): mixed
{
    $fila = consulta($conn, $sql, $tipos, $params)[0] ?? null;
    return $fila === null ? null : reset($fila);
}

/** Datos de paginación a partir del total de filas y el parámetro ?pagina=. */
function paginar(int $total, int $por_pagina = 12): array
{
    $paginas = max(1, (int) ceil($total / $por_pagina));
    $pagina = min(max(1, (int) ($_GET['pagina'] ?? 1)), $paginas);
    return ['total' => $total, 'por' => $por_pagina, 'paginas' => $paginas, 'pagina' => $pagina, 'desde' => ($pagina - 1) * $por_pagina];
}