<?php
require_once __DIR__ . '/includes/app.php';

if (isset($_POST['submit'])) {
   $name = trim($_POST['name']);
   $email = trim($_POST['email']);
   // Siempre se crea como usuario normal: el rol admin no se puede elegir desde el formulario.
   $user_type = 'user';

   if (!hash_equals($_POST['password'], $_POST['cpassword'])) {
      $message[] = '¡La confirmación de la contraseña no coincide!';
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
?>

<div class="form-container">

   <form action="" method="post">
      <div class="marca">papelería U.C.P.</div>
      <h3>Crea tu cuenta</h3>
      <input type="text" name="name" placeholder="Nombre" autocomplete="name" required class="caja">
      <input type="email" name="email" placeholder="Correo electrónico" autocomplete="email" required class="caja">
      <input type="password" name="password" placeholder="Contraseña" autocomplete="new-password" required class="caja">
      <input type="password" name="cpassword" placeholder="Confirma tu contraseña" autocomplete="new-password" required class="caja">
      <input type="submit" name="submit" value="Registrarme" class="boton">
      <p>¿Ya tienes una cuenta? <a href="login.php">Inicia sesión</a></p>
   </form>

</div>

</body>
</html>
