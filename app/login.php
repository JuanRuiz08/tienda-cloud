<?php
require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/autenticacion.php';

iniciar_sesion();

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
      <h3>iniciar sesión ahora en papeleria U.C.P</h3>
      <input type="email" name="email" placeholder="ingresa tu correo electrónico" required class="caja">
      <input type="password" name="password" placeholder="ingresa tu contraseña" required class="caja">
      <input type="submit" name="submit" value="iniciar sesión ahora" class="boton">
      <p>¿no tienes una cuenta? <a href="registro.php">regístrate ahora</a></p>
   </form>

</div>

</body>
</html>
