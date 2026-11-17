<?php
require_once __DIR__ . '/includes/app.php';

requerir_admin();

const IMAGEN_MAX_BYTES = 2000000;
const CARPETA_IMAGENES = 'uploaded_img';

/** Valida y guarda una imagen subida. Devuelve [nombre_guardado, null] o [null, mensaje_de_error]. */
function recibir_imagen(array $archivo): array
{
   if ($archivo['size'] > IMAGEN_MAX_BYTES) {
      return [null, 'El tamaño de la imagen es demasiado grande'];
   }
   $imagen = guardar_imagen_subida($archivo, CARPETA_IMAGENES);
   return $imagen === null ? [null, 'La imagen no es válida (solo JPG o PNG)'] : [$imagen, null];
}

function borrar_imagen(string $nombre): void
{
   @unlink(CARPETA_IMAGENES . '/' . basename($nombre));
}

/** Alta de producto. Devuelve el aviso a mostrar. */
function alta_producto(mysqli $conn): string
{
   $nombre = trim($_POST['name']);

   if (valor($conn, 'SELECT COUNT(*) FROM `products` WHERE name = ?', 's', [$nombre])) {
      return 'El nombre del producto ya ha sido añadido';
   }
   [$imagen, $error] = recibir_imagen($_FILES['image']);
   if ($error) {
      return $error;
   }

   consulta($conn, 'CALL agregar_producto(?, ?, ?, ?, ?, ?)', 'sissis', [
      $nombre, (int) $_POST['price'], $_POST['category'], $imagen, (int) $_POST['stock'], trim($_POST['description']),
   ]);
   app_log('info', "producto añadido: $nombre (imagen $imagen)");
   return 'Producto añadido exitosamente';
}

/** Edición de producto. Devuelve un aviso de error, o null si se guardó. */
function editar_producto(mysqli $conn): ?string
{
   $imagen = $_POST['update_old_image'];

   if (!empty($_FILES['update_image']['name'])) {
      [$nueva, $error] = recibir_imagen($_FILES['update_image']);
      if ($error) {
         return $error;
      }
      borrar_imagen($imagen);
      $imagen = $nueva;
   }

   consulta($conn, 'CALL actualizar_producto_con_stock(?, ?, ?, ?, ?)', 'isisi', [
      (int) $_POST['update_p_id'], trim($_POST['update_name']), (int) $_POST['update_price'], $imagen, (int) $_POST['update_stock'],
   ]);
   return null;
}

function baja_producto(mysqli $conn, int $id): void
{
   $imagen = valor($conn, 'SELECT image FROM `products` WHERE id = ?', 'i', [$id]);
   if ($imagen !== null) {
      borrar_imagen($imagen);
   }
   consulta($conn, 'CALL eliminar_producto(?)', 'i', [$id]);
}

if (isset($_POST['add_product'])) {
   $message[] = alta_producto($conn);
}

if (isset($_POST['update_product'])) {
   if ($error = editar_producto($conn)) {
      $message[] = $error;
   } else {
      redirigir('admin_productos.php');
   }
}

if (isset($_GET['delete'])) {
   baja_producto($conn, (int) $_GET['delete']);
   redirigir('admin_productos.php');
}

$productos = consulta($conn, 'SELECT * FROM `products`');
$en_edicion = isset($_GET['update'])
   ? (consulta($conn, 'SELECT * FROM `products` WHERE id = ?', 'i', [(int) $_GET['update']])[0] ?? null)
   : null;

render_head('Productos', 'admin_style.css');
include 'admin_header.php';
?>

<section class="add-products" id="nuevo-producto">

   <h1 class="title">Productos de la tienda</h1>

   <form action="" method="post" enctype="multipart/form-data">
      <h3>Añadir producto</h3>
      <input type="text" name="name" class="caja" placeholder="Ingrese el nombre del producto" required>
      <input type="text" name="description" class="caja" placeholder="Ingrese la descripcion del producto" required>
      <input type="number" min="0" name="price" class="caja" placeholder="Precio en pesos (ej. 10000)" required>
      <input type="file" name="image" accept="image/jpg, image/jpeg, image/png" class="caja" required>
      <input type="number" min="0" name="stock" class="caja" placeholder="Ingrese el stock del producto" required>
      <select name="category" class="caja" required>
         <option value="">Selecciona una categoría</option>
         <option value="dulceria">Dulcería</option>
         <option value="uso personal">Uso Personal</option>
         <option value="escolar">Escolar</option>
      </select>
      <input type="submit" value="Añadir producto" name="add_product" class="boton">
   </form>

   <section class="show-products">
      <div class="caja-container">
         <?php if (!$productos): ?>
            <p class="vacio">¡No se han añadido productos aún!</p>
         <?php endif; ?>

         <?php foreach ($productos as $producto): ?>
         <div class="caja" id="producto-<?= (int) $producto['id'] ?>">
            <img src="uploaded_img/<?= e($producto['image']) ?>" alt="" class="image">
            <div class="name"><?= e($producto['name']) ?></div>
            <div class="price"><?= precio($producto['price']) ?></div>
            <div class="stock">Stock: <?= e($producto['stock']) ?></div>
            <a href="admin_productos.php?update=<?= (int) $producto['id'] ?>" class="boton-opcion">Actualizar</a>
            <a href="admin_productos.php?delete=<?= (int) $producto['id'] ?>" class="boton-eliminar" onclick="return confirm('¿Eliminar este producto?');">Eliminar</a>
         </div>
         <?php endforeach; ?>
      </div>
   </section>

   <?php if ($en_edicion): ?>
   <section class="edit-product-form">
      <form action="" method="post" enctype="multipart/form-data">
         <input type="hidden" name="update_p_id" value="<?= (int) $en_edicion['id'] ?>">
         <input type="hidden" name="update_old_image" value="<?= e($en_edicion['image']) ?>">
         <img src="uploaded_img/<?= e($en_edicion['image']) ?>" alt="">
         <h3>Editar producto</h3>
         <label class="campo"><span>Nombre</span><input type="text" name="update_name" value="<?= e($en_edicion['name']) ?>" class="caja" required></label>
         <label class="campo"><span>Precio (en pesos)</span><input type="number" name="update_price" value="<?= e($en_edicion['price']) ?>" min="0" class="caja" required></label>
         <label class="campo"><span>Stock</span><input type="number" name="update_stock" value="<?= e($en_edicion['stock']) ?>" min="0" class="caja" required></label>
         <label class="campo"><span>Nueva imagen (opcional)</span><input type="file" name="update_image" accept="image/jpg, image/jpeg, image/png" class="caja"></label>
         <input type="submit" value="Actualizar producto" name="update_product" class="boton">
         <a href="admin_productos.php" class="boton-opcion">Cancelar</a>
      </form>
   </section>
   <?php endif; ?>

</section>

<?php render_cierre('admin_script.js', false); ?>
