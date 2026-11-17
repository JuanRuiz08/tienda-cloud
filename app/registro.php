<?php
require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/acceso.php';

iniciar_sesion();   // el token CSRF del formulario vive en la sesión

if (isset($_POST['submit'])) {
   $name = trim($_POST['name']);
   $email = trim($_POST['email']);
   // Siempre se crea como usuario normal: el rol admin no se puede elegir desde el formulario.
   $user_type = 'user';

   if (!hash_equals($_POST['password'], $_POST['cpassword'])) {
      $message[] = '¡La confirmación de la contraseña no coincide!';
   } elseif ($error_clave = validar_clave($_POST['password'])) {
      $message[] = $error_clave;
   } elseif (mb_strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $message[] = 'Escribe tu nombre y un correo electrónico válido.';
   } else {
      try {
         consulta($conn, 'CALL registerUser(?, ?, ?, ?)', 'ssss',
            [$name, $email, password_hash($_POST['password'], PASSWORD_DEFAULT), $user_type]);
         app_log('info', 'usuario registrado: ' . $email);
         redirigir('login.php');
      } catch (mysqli_sql_exception $e) {
         $message[] = $e->getMessage();
      }
   }
}

render_head('Registro');
render_mensajes($message ?? []);

acceso_abrir('Crea tu cuenta', 'Regístrate para pedir desde el campus.');
?>
         <form action="" method="post">
            <?= csrf_campo() ?>
<?php
acceso_campo('text', 'name', 'Nombre', 'fa-user', 'name', $name ?? '');
acceso_campo('email', 'email', 'Correo electrónico', 'fa-envelope', 'email', $email ?? '');
acceso_campo('password', 'password', 'Contraseña (mínimo 8, con letras y números)', 'fa-lock', 'new-password', '', 'minlength="8"');
acceso_campo('password', 'cpassword', 'Confirma tu contraseña', 'fa-lock', 'new-password');
?>
            <input type="submit" name="submit" value="Crear cuenta" class="boton">
         </form>

         <p class="cambio">¿Ya tienes una cuenta? <a href="login.php">Inicia sesión</a></p>
<?php
acceso_cerrar();
render_cierre('acceso.js', false);