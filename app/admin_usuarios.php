<?php
require_once __DIR__ . '/includes/app.php';

requerir_admin();

if (isset($_GET['delete'])) {
   consulta($conn, 'DELETE FROM `users` WHERE id = ?', 'i', [(int) $_GET['delete']]);
   redirigir('admin_usuarios.php');
}

$usuarios = consulta($conn, 'SELECT * FROM `users`');

render_head('Usuarios', 'admin_style.css');
include 'admin_header.php';
?>

<section class="usuarios">

   <h1 class="title"> Cuentas de usuarios </h1>

   <div class="caja-container">
      <?php foreach ($usuarios as $usuario): ?>
      <div class="caja">
         <p> ID de usuario : <span><?= e($usuario['id']) ?></span> </p>
         <p> Nombre de usuario : <span><?= e($usuario['name']) ?></span> </p>
         <p> Correo electrónico : <span><?= e($usuario['email']) ?></span> </p>
         <p> Tipo de usuario : <span style="color:<?= $usuario['user_type'] === 'admin' ? 'var(--orange)' : '' ?>"><?= e($usuario['user_type']) ?></span> </p>
         <a href="admin_usuarios.php?delete=<?= (int) $usuario['id'] ?>" onclick="return confirm('¿Eliminar este usuario?');" class="boton-eliminar">Eliminar usuario</a>
      </div>
      <?php endforeach; ?>
   </div>

</section>

<?php render_cierre('admin_script.js', false); ?>
