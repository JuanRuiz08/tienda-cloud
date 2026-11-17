<?php
/** Piezas compartidas por las pantallas de acceso (inicio de sesión y registro). */

/** Abre la pantalla: panel con la marca a la izquierda y, a la derecha, el encabezado del formulario. */
function acceso_abrir(string $titulo, string $subtitulo): void
{
    ?>
<main class="acceso">

   <aside class="acceso-lado">
      <a href="inicio.php" class="acceso-marca">papelería U.C.P.</a>

      <div class="acceso-copy">
         <h2>Todo para tu estudio, en un solo lugar</h2>
         <p>Útiles escolares, dulcería y artículos de uso personal, sin salir del campus.</p>
         <ul class="ventajas">
            <li><i class="fas fa-boxes"></i> Catálogo con stock en tiempo real</li>
            <li><i class="fas fa-receipt"></i> Sigue el estado de tus pedidos</li>
            <li><i class="fas fa-lock"></i> Paga con tarjeta o en efectivo</li>
         </ul>
      </div>

      <p class="acceso-pie">Universidad Católica de Pereira</p>
   </aside>

   <section class="acceso-form">
      <div class="acceso-caja">
         <h1><?= e($titulo) ?></h1>
         <p class="sub"><?= e($subtitulo) ?></p>
<?php
}

/** Cierra la pantalla: enlace para seguir mirando la tienda sin cuenta. */
function acceso_cerrar(): void
{
    ?>
         <a href="inicio.php" class="volver"><i class="fas fa-arrow-left"></i> Seguir viendo la tienda</a>
      </div>
   </section>

</main>
<?php
}

/** Campo de formulario con etiqueta e icono; las contraseñas llevan un botón para mostrarlas u ocultarlas. */
function acceso_campo(string $tipo, string $nombre, string $etiqueta, string $icono, string $autocompletar, string $valor = '', string $atributos = ''): void
{
    $clave = $tipo === 'password';
    ?>
            <label class="campo">
               <span><?= e($etiqueta) ?></span>
               <div class="entrada<?= $clave ? ' con-ver' : '' ?>">
                  <i class="fas <?= e($icono) ?> icono"></i>
                  <input type="<?= e($tipo) ?>" name="<?= e($nombre) ?>" value="<?= e($valor) ?>" autocomplete="<?= e($autocompletar) ?>" <?= $atributos ?> required>
                  <?php if ($clave): ?>
                  <button type="button" class="ver-clave" aria-label="Mostrar contraseña"><i class="fas fa-eye"></i></button>
                  <?php endif; ?>
               </div>
            </label>
<?php
}