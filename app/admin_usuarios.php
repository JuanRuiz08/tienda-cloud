<?php
require_once __DIR__ . '/includes/app.php';

$admin_id = requerir_admin();

if (isset($_POST['eliminar'])) {
   $id = (int) $_POST['eliminar'];
   $usuario = consulta($conn, 'SELECT id, name, email, user_type FROM `users` WHERE id = ?', 'i', [$id])[0] ?? null;

   if ($usuario === null) {
      $message[] = 'La cuenta no existe.';
   } elseif ($id === $admin_id) {
      $message[] = 'No puedes eliminar tu propia cuenta.';
   } elseif ($usuario['user_type'] === 'admin') {
      $message[] = 'Las cuentas de administrador no se eliminan desde aquí.';
   } else {
      // Al borrar una cuenta también se vacía su carrito, para no dejar filas huérfanas.
      consulta($conn, 'DELETE FROM `cart` WHERE user_id = ?', 'i', [$id]);
      consulta($conn, 'DELETE FROM `users` WHERE id = ?', 'i', [$id]);
      auditar($conn, 'usuario_eliminado', "Cuenta #$id ({$usuario['email']})");
      $message[] = 'Cuenta de ' . $usuario['email'] . ' eliminada.';
   }
}

$pag = paginar((int) valor($conn, 'SELECT COUNT(*) FROM `users`'), 12);
$usuarios = consulta($conn, 'SELECT * FROM `users` ORDER BY id LIMIT ? OFFSET ?', 'ii', [$pag['por'], $pag['desde']]);

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
         <p> Tipo de usuario : <span class="rol-<?= e($usuario['user_type']) ?>"><?= e($usuario['user_type']) ?></span> </p>
         <?php if ($usuario['user_type'] !== 'admin'): ?>
         <?= form_borrar('eliminar', (int) $usuario['id'], 'Eliminar usuario', '¿Eliminar la cuenta de ' . $usuario['email'] . '?') ?>
         <?php endif; ?>
      </div>
      <?php endforeach; ?>
   </div>

   <?php render_paginador($pag); ?>

</section>

<?php render_cierre('admin_script.js', false); ?>
