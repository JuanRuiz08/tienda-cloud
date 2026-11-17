<?php
/**
 * Ejecutor de migraciones: aplica, en orden, los archivos de /opt/migrations (database/migrations/) que
 * todavía no se hayan aplicado y los registra en la tabla schema_migrations. Se ejecuta en cada arranque
 * del contenedor (local y Railway), así que un cambio de esquema llega a todos los entornos sin pasos manuales.
 *
 * Cada migración es un archivo .php que devuelve una función: return function (mysqli $db): void { ... };
 * Las migraciones deben ser seguras de repetir (usan columna_existe(), tabla_existe(), etc.).
 */
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$carpeta = $argv[1] ?? '/opt/migrations';
$h = getenv('DB_HOST'); $u = getenv('DB_USER'); $p = getenv('DB_PASS'); $n = getenv('DB_NAME');
if (!$h || !is_dir($carpeta)) { exit(0); }

$db = null;
for ($i = 1; $i <= 30; $i++) {
    try { $db = new mysqli($h, $u, $p, $n); break; }
    catch (mysqli_sql_exception $e) { fwrite(STDERR, "[migrar] esperando a la base ($i/30): {$e->getMessage()}\n"); sleep(2); }
}
if (!$db) { fwrite(STDERR, "[migrar] ERROR: no hay conexión\n"); exit(1); }
$db->set_charset('utf8mb4');
$db->query("SET collation_connection = utf8mb4_general_ci");

function tabla_existe(mysqli $db, string $tabla): bool
{
    $st = $db->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
    $st->bind_param('s', $tabla); $st->execute(); $st->bind_result($n); $st->fetch(); $st->close();
    return $n > 0;
}
function columna_existe(mysqli $db, string $tabla, string $columna): bool
{
    $st = $db->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
    $st->bind_param('ss', $tabla, $columna); $st->execute(); $st->bind_result($n); $st->fetch(); $st->close();
    return $n > 0;
}
function indice_existe(mysqli $db, string $tabla, string $indice): bool
{
    $st = $db->prepare('SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?');
    $st->bind_param('ss', $tabla, $indice); $st->execute(); $st->bind_result($n); $st->fetch(); $st->close();
    return $n > 0;
}

if (!tabla_existe($db, 'orders')) {
    fwrite(STDERR, "[migrar] el esquema base todavía no existe; nada que migrar\n");
    exit(0);
}

$db->query('CREATE TABLE IF NOT EXISTS schema_migrations (
    version VARCHAR(120) NOT NULL PRIMARY KEY,
    aplicada_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci');

$hechas = array_column($db->query('SELECT version FROM schema_migrations')->fetch_all(MYSQLI_ASSOC), 'version');
$archivos = glob(rtrim($carpeta, '/') . '/*.php');
sort($archivos);

$aplicadas = 0;
foreach ($archivos as $archivo) {
    $version = basename($archivo, '.php');
    if (in_array($version, $hechas, true)) { continue; }

    fwrite(STDERR, "[migrar] aplicando $version\n");
    try {
        $migracion = require $archivo;
        $migracion($db);
        $st = $db->prepare('INSERT INTO schema_migrations (version) VALUES (?)');
        $st->bind_param('s', $version); $st->execute(); $st->close();
        $aplicadas++;
    } catch (Throwable $e) {
        fwrite(STDERR, "[migrar] ERROR en $version: {$e->getMessage()}\n");
        exit(1);   // no se continúa: las siguientes migraciones pueden depender de esta
    }
}
fwrite(STDERR, "[migrar] listo: $aplicadas migración(es) aplicada(s), " . count($hechas) . " ya estaban\n");