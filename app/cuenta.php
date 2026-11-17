<?php
require_once __DIR__ . '/includes/app.php';

iniciar_sesion();
$es_admin = !empty($_SESSION['admin_id']);
$usuario_id = $es_admin ? (int) $_SESSION['admin_id'] : (int) ($_SESSION['user_id'] ?? 0);
if ($usuario_id === 0) {
   redirigir('login.php?requiere=1');
}
$cuenta = consulta($conn, 'SELECT id, name, email, password FROM `users` WHERE id = ?', 'i', [$usuario_id])[0] ?? null;
if ($cuenta === null) {
   redirigir('cerrar_sesion.php');
}

if (isset($_POST['cambiar_clave'])) {
   $actual = (string) ($_POST['actual'] ?? '');
   $nueva = (string) ($_POST['nueva'] ?? '');
   $espera = login_bloqueado_segundos($conn, $cuenta['email']);

   if ($espera > 0) {
      $message[] = 'Demasiados intentos. Vuelve a intentarlo en ' . ceil($espera / 60) . ' min.';
   } elseif (!password_verify($actual, $cuenta['password'])) {
      login_registrar_fallo($conn, $cuenta['email']);
      $message[] = 'La contraseña actual no es correcta.';
   } elseif (!hash_equals($nueva, (string) ($_POST['confirmar'] ?? ''))) {
      $message[] = 'La confirmación no coincide con la contraseña nueva.';
   } elseif ($error = validar_clave($nueva)) {
      $message[] = $error;
   } elseif ($nueva === $actual) {
      $message[] = 'La contraseña nueva debe ser distinta de la actual.';
   } else {
      consulta($conn, 'UPDATE `users` SET password = ? WHERE id = ?', 'si', [password_hash($nueva, PASSWORD_DEFAULT), $usuario_id]);
      login_limpiar($conn, $cuenta['email']);
      session_regenerate_id(true);
      app_log('info', "contraseña cambiada user_id=$usuario_id");
      if ($es_admin) {
         auditar($conn, 'clave_cambiada', 'El administrador cambió su contraseña');
      }
      $message[] = '¡Contraseña actualizada!';
   }
}

if ($es_admin) {
   render_head('Mi cuenta', 'admin_style.css');
   include 'admin_header.php';
} else {
   render_head('Mi cuenta');
   include 'header.php';
   render_mensajes($message ?? []);
   render_encabezado('Mi cuenta', 'Mi cuenta');
}
?>

<section class="cuenta">
   <?php if ($es_admin): ?><h1 class="title">Mi cuenta</h1><?php endif; ?>

   <div class="cuenta-grid">

      <div class="panel-card">
         <h2>Datos de la cuenta</h2>
         <p class="sub">Así te identifica la tienda.</p>
         <dl class="datos-cuenta">
            <dt>Nombre</dt><dd><?= e($cuenta['name']) ?></dd>
            <dt>Correo</dt><dd><?= e($cuenta['email']) ?></dd>
            <dt>Tipo</dt><dd><?= $es_admin ? 'Administrador' : 'Cliente' ?></dd>
         </dl>
      </div>

      <div class="panel-card">
         <h2>Cambiar contraseña</h2>
         <p class="sub">Mínimo 8 caracteres, con letras y números.</p>
         <form action="" method="post" class="form-cuenta">
            <?= csrf_campo() ?>
            <label class="campo"><span>Contraseña actual</span><input type="password" name="actual" autocomplete="current-password" required></label>
            <label class="campo"><span>Contraseña nueva</span><input type="password" name="nueva" autocomplete="new-password" minlength="8" required></label>
            <label class="campo"><span>Confirma la contraseña nueva</span><input type="password" name="confirmar" autocomplete="new-password" minlength="8" required></label>
            <input type="submit" name="cambiar_clave" value="Guardar contraseña" class="boton">
         </form>
      </div>

      <?php if ($es_admin): ?>
      <div class="panel-card">
         <h2>Copia de seguridad</h2>
         <p class="sub">Descarga un archivo .sql con usuarios, productos, pedidos y carritos. Guárdalo en un lugar seguro: incluye los hashes de las contraseñas.</p>
         <form action="admin_backup.php" method="post">
            <?= csrf_campo() ?>
            <button type="submit" class="boton-opcion"><i class="fas fa-download"></i> Descargar copia (.sql)</button>
         </form>
      </div>
      <?php endif; ?>

   </div>
</section>

<?php render_cierre($es_admin ? 'admin_script.js' : 'script.js', !$es_admin); ?>