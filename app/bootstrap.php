<?php
/**
 * bootstrap.php - se ejecuta antes de cada script (auto_prepend_file en php-fpm).
 *
 * Aporta tres cosas que el monolito original no tenía:
 *   1. Configuración leída solo desde variables de entorno.
 *   2. Logs hacia stderr (salida estándar del contenedor) y no a archivos locales.
 *   3. Sesiones guardadas en MySQL, para que el contenedor no guarde estado (stateless).
 */

/** Lee una variable de entorno obligatoria; si falta, registra el error y responde 500. */
function env_requerida(string $nombre): string
{
    $valor = getenv($nombre);
    if ($valor === false) {
        app_log('error', "Falta la variable de entorno obligatoria $nombre");
        http_response_code(500);
        exit('Error de configuración del servidor.');
    }
    return $valor;
}

/** Escribe una línea de log en stderr; Docker la expone con "docker compose logs". */
function app_log(string $nivel, string $mensaje): void
{
    $linea = sprintf("[%s] [app] %s: %s\n", date('Y-m-d H:i:s'), strtoupper($nivel), $mensaje);
    file_put_contents('php://stderr', $linea);
}

/** Abre una conexión nueva a MySQL con reintentos (la BD puede tardar en arrancar). */
function abrir_conexion_bd(int $intentos = 10): mysqli
{
    $host = env_requerida('DB_HOST');
    $user = env_requerida('DB_USER');
    $pass = env_requerida('DB_PASS');
    $name = env_requerida('DB_NAME');

    for ($i = 1; $i <= $intentos; $i++) {
        try {
            $c = new mysqli($host, $user, $pass, $name);
            $c->set_charset('utf8mb4');
            return $c;
        } catch (mysqli_sql_exception $e) {
            app_log('warning', "No se pudo conectar a la BD (intento $i/$intentos): " . $e->getMessage());
            sleep(2);
        }
    }
    app_log('error', 'La base de datos no está disponible.');
    http_response_code(503);
    exit('Servicio no disponible. Intenta de nuevo en unos segundos.');
}

/**
 * Manejador de sesiones en MySQL. Con esto cualquier réplica del contenedor
 * puede atender al mismo usuario: la sesión vive en la BD, no en el disco local.
 */
class SesionesEnBD implements SessionHandlerInterface
{
    private ?mysqli $db = null;

    private function bd(): mysqli
    {
        if ($this->db === null) {
            $this->db = abrir_conexion_bd();
        }
        return $this->db;
    }

    public function open(string $path, string $name): bool { return true; }
    public function close(): bool { return true; }

    public function read(string $id): string|false
    {
        $st = $this->bd()->prepare('SELECT data FROM sessions WHERE id = ?');
        $st->bind_param('s', $id);
        $st->execute();
        $st->bind_result($data);
        $existe = $st->fetch();
        $st->close();
        return $existe ? (string) $data : '';
    }

    public function write(string $id, string $data): bool
    {
        $ahora = time();
        $st = $this->bd()->prepare(
            'INSERT INTO sessions (id, data, updated_at) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE data = VALUES(data), updated_at = VALUES(updated_at)'
        );
        $st->bind_param('ssi', $id, $data, $ahora);
        $ok = $st->execute();
        $st->close();
        return $ok;
    }

    public function destroy(string $id): bool
    {
        $st = $this->bd()->prepare('DELETE FROM sessions WHERE id = ?');
        $st->bind_param('s', $id);
        $ok = $st->execute();
        $st->close();
        return $ok;
    }

    public function gc(int $max_lifetime): int|false
    {
        $limite = time() - $max_lifetime;
        $st = $this->bd()->prepare('DELETE FROM sessions WHERE updated_at < ?');
        $st->bind_param('i', $limite);
        $st->execute();
        $borradas = $st->affected_rows;
        $st->close();
        return $borradas;
    }
}

/** ¿La petición llegó por HTTPS? (Railway termina el TLS en su proxy y avisa con X-Forwarded-Proto.) */
function https_activo(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

if (PHP_SAPI !== 'cli') {
    // La cookie de sesión solo viaja por HTTPS (en local, sin HTTPS, sigue funcionando por HTTP).
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => https_activo(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_set_save_handler(new SesionesEnBD(), true);
}

/**
 * Valida y guarda una imagen subida. Devuelve el nombre final o null si no es válida.
 * Evita que se suban archivos ejecutables (.php) a la carpeta pública de imágenes.
 */
function guardar_imagen_subida(array $archivo, string $carpeta): ?string
{
    if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }
    $ext = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
        return null;
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($archivo['tmp_name']);
    if (!in_array($mime, ['image/jpeg', 'image/png'], true)) {
        return null;
    }
    $base = preg_replace('/[^A-Za-z0-9_-]/', '_', pathinfo($archivo['name'], PATHINFO_FILENAME));
    $nombre = substr(uniqid(), -6) . '_' . substr($base, 0, 40) . '.' . $ext;
    if (!move_uploaded_file($archivo['tmp_name'], rtrim($carpeta, '/') . '/' . $nombre)) {
        app_log('error', 'No se pudo guardar la imagen subida en ' . $carpeta);
        return null;
    }
    return $nombre;
}
