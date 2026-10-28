<?php
// RF-01: Inicio de sesión con correo institucional y contraseña.
if (usuario()) redirigir('inicio');

$error = null;
$correo = '';
if (es_post()) {
    csrf_verificar();
    $correo = p('correo');
    $error = intentar_login($correo, (string)($_POST['clave'] ?? ''));
    if ($error === null) redirigir('inicio');
}
pagina_inicio('Iniciar sesión');
?>
<div class="login">
  <div class="login-lado">
    <div class="marca-sello grande">SJ</div>
    <h2>Biblioteca escolar</h2>
    <p>Institución Educativa Pública San Juan<br>San Juan Bautista · Ayacucho</p>
  </div>
  <form method="post" class="login-form" action="<?= e(url('login')) ?>">
    <?= csrf_campo() ?>
    <h1>Iniciar sesión</h1>
    <p class="sub">Ingrese con su correo institucional.</p>
    <?php if ($error): ?><div class="aviso aviso-error"><?= e($error) ?></div><?php endif; ?>
    <label>Correo institucional
      <input type="email" name="correo" value="<?= e($correo) ?>" required autofocus autocomplete="username" placeholder="nombre@iesanjuan.edu.pe">
    </label>
    <label>Contraseña
      <input type="password" name="clave" required autocomplete="current-password">
    </label>
    <button class="btn btn-primario btn-bloque">Ingresar</button>
    <a class="enlace-sec" href="<?= e(url('recuperar')) ?>">¿Olvidó su contraseña?</a>
    <p class="nota">Si es su primer ingreso, su contraseña inicial es su número de DNI.</p>
  </form>
</div>
<?php pagina_fin();
