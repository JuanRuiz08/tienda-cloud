<?php
require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/autenticacion.php';

iniciar_sesion();

if (isset($_GET['requiere'])) {
   $message[] = 'Inicia sesión o regístrate para continuar.';
}

if (isset($_POST['submit'])) {
   $email = trim($_POST['email']);
   $usuario = autenticar($conn, $email, $_POST['password']);

   if ($usuario) {
      redirigir(abrir_sesion_de($usuario));
   }
   app_log('warning', 'login fallido para el correo ' . $email);
   $message[] = '¡correo electrónico o contraseña incorrectos!';
}

render_head('iniciar sesión');
render_mensajes($message ?? []);
?>

<div class="form-container">

   <form action="" method="post">
      <div class="marca">papelería U.C.P.</div>
      <h3>Inicia sesión</h3>
      <input type="email" name="email" placeholder="Correo electrónico" autocomplete="email" required class="caja">
      <input type="password" name="password" placeholder="Contraseña" autocomplete="current-password" required class="caja">
      <input type="submit" name="submit" value="Iniciar sesión" class="boton">
      <p>¿No tienes una cuenta? <a href="registro.php">Regístrate</a></p>
   </form>

</div>

</body>
</html>
