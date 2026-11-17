<?php
require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/autenticacion.php';
require_once __DIR__ . '/includes/acceso.php';

iniciar_sesion();

if (isset($_GET['requiere'])) {
   $message[] = 'Inicia sesión o regístrate para continuar.';
}

if (isset($_POST['submit'])) {
   $email = trim($_POST['email']);
   $espera = login_bloqueado_segundos($conn, $email);

   if ($espera > 0) {
      // Demasiados fallos seguidos: ni siquiera se comprueba la contraseña.
      app_log('warning', "login bloqueado por exceso de intentos para $email (" . ip_cliente() . ')');
      $message[] = 'Demasiados intentos fallidos. Vuelve a intentarlo en ' . ceil($espera / 60) . ' min.';
   } else {
      $usuario = autenticar($conn, $email, $_POST['password']);

      if ($usuario) {
         login_limpiar($conn, $email);
         redirigir(abrir_sesion_de($usuario));
      }
      login_registrar_fallo($conn, $email);
      app_log('warning', 'login fallido para el correo ' . $email);
      $message[] = '¡correo electrónico o contraseña incorrectos!';
   }
}

render_head('iniciar sesión');
render_mensajes($message ?? []);

acceso_abrir('Bienvenido de nuevo', 'Inicia sesión para hacer tus pedidos.');
?>
         <form action="" method="post">
            <?= csrf_campo() ?>
<?php
acceso_campo('email', 'email', 'Correo electrónico', 'fa-envelope', 'email', $email ?? '');
acceso_campo('password', 'password', 'Contraseña', 'fa-lock', 'current-password');
?>
            <input type="submit" name="submit" value="Iniciar sesión" class="boton">
         </form>

         <p class="cambio">¿No tienes una cuenta? <a href="registro.php">Regístrate</a></p>
<?php
acceso_cerrar();
render_cierre('acceso.js', false);