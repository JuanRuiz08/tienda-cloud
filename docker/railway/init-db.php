<?php
// Carga database/init/01_shop_db.sql si la base está vacía (Railway no ejecuta docker-entrypoint-initdb.d).
// Se usa mysqli (no el cliente "mysql") para soportar el login caching_sha2_password de MySQL 8.
mysqli_report(MYSQLI_REPORT_OFF);
$h = getenv('DB_HOST'); $u = getenv('DB_USER'); $p = getenv('DB_PASS'); $n = getenv('DB_NAME');
$archivo = '/opt/init/01_shop_db.sql';
if (!$h || !is_file($archivo)) { exit(0); }

$db = null;
for ($i = 1; $i <= 30; $i++) {
    $db = @new mysqli($h, $u, $p, $n);
    if (!$db->connect_errno) { break; }
    fwrite(STDERR, "[init-db] esperando a la base de datos ($i/30): {$db->connect_error}\n");
    sleep(2);
}
if (!$db || $db->connect_errno) { fwrite(STDERR, "[init-db] ERROR: no hay conexión\n"); exit(1); }
$db->set_charset('utf8mb4');
$db->query("SET collation_connection = utf8mb4_general_ci");

// El esquema usa utf8mb4_general_ci (como el mysql de docker-compose). El MySQL administrado de Railway
// trae utf8mb4_0900_ai_ci por defecto y los procedimientos heredan el collation de la base: sin esto
// falla con "Illegal mix of collations".
$db->query("ALTER DATABASE `$n` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");

if ($db->query("SHOW TABLES LIKE 'products'")->num_rows > 0) {
    fwrite(STDERR, "[init-db] la base ya tiene datos, no se importa\n");
    exit(0);
}

fwrite(STDERR, "[init-db] base vacía: importando $archivo\n");
$delim = ';'; $buf = ''; $n_ok = 0;
foreach (file($archivo) as $linea) {
    if (preg_match('/^DELIMITER\s+(\S+)/i', $linea, $m)) { $delim = $m[1]; continue; }
    $buf .= $linea;
    if (substr(rtrim($buf), -strlen($delim)) === $delim) {
        $sql = trim(substr(rtrim($buf), 0, -strlen($delim)));
        $buf = '';
        if ($sql === '' || preg_match('/^--/', $sql) && strpos($sql, "\n") === false) { continue; }
        if (!$db->query($sql)) {
            fwrite(STDERR, "[init-db] ERROR en sentencia: {$db->error}\n" . substr($sql, 0, 120) . "\n");
            exit(1);
        }
        $n_ok++;
    }
}
fwrite(STDERR, "[init-db] base inicializada ($n_ok sentencias)\n");