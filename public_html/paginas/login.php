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
  <form method="post" class="login-form" action="<?= e(url('login')) ?>">
    <?= csrf_campo() ?>
    <div class="login-identidad"><img src="assets/img/insignia-san-juan.png" alt="Escudo oficial I.E. San Juan"><div><span class="sobrelinea">Portal institucional seguro</span><h1>Acceso a la Biblioteca</h1><p class="sub">Sistema de préstamos y consulta bibliográfica</p></div></div>
    <div class="tabs" aria-label="Tipo de acceso"><button type="button" class="activo" data-rol-acceso="estudiante" aria-pressed="true"><?= icono('school') ?> Estudiante</button><button type="button" data-rol-acceso="personal" aria-pressed="false"><?= icono('person') ?> Docente / Personal</button></div>
    <?php if ($error): ?><div class="aviso aviso-error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <label>Correo institucional<div class="campo-icono"><?= icono('mail') ?><input type="email" name="correo" value="<?= e($correo) ?>" required autofocus autocomplete="username" placeholder="estudiante@iesanjuan.edu.pe"></div></label>
    <div class="label-fila"><label for="clave-login">Contraseña institucional</label><a href="<?= e(url('recuperar')) ?>">¿Olvidaste tu contraseña?</a></div>
    <div class="campo-icono"><?= icono('key') ?><input type="password" name="clave" id="clave-login" required autocomplete="current-password" placeholder="Ingresa tu clave de acceso"></div>
    <label class="check recordar-correo"><input type="checkbox" id="recordar-correo"> Recordar mi correo en este equipo</label>
    <div class="login-nota"><?= icono('info') ?><div><strong>Primer ingreso escolar</strong><p id="nota-acceso">Su contraseña inicial es su número de DNI. Ingrese con el correo registrado en la biblioteca y actualice su clave al acceder por primera vez.</p></div></div>
    <button class="btn btn-primario btn-bloque">Ingresar al Sistema <?= icono('arrow') ?></button>
    <p class="login-ayuda"><a href="#ayuda-acceso">¿Problemas con tu cuenta? Consulta la orientación al lector</a></p>
  </form>
  <aside class="login-aside">
    <section class="reglamento"><span class="sobrelinea"><?= icono('school') ?> Comunidad sanjuanista · <?= date('Y') ?></span><h2>Excelencia, Estudio y Disciplina</h2><p>Explora los recursos de aprendizaje de tu biblioteca y acompaña tu formación con nuevas lecturas.</p><ul><li><?= icono('book') ?><div><strong>Catálogo bibliográfico escolar</strong><small>Consulta títulos, autores y disponibilidad de materiales.</small></div></li><li><?= icono('calendar') ?><div><strong>Préstamos y devoluciones</strong><small>Revisa las fechas de entrega y mantén tus lecturas al día.</small></div></li><li><?= icono('school') ?><div><strong>Lectura para toda la comunidad</strong><small>Un espacio para estudiantes, docentes y personal de biblioteca.</small></div></li></ul></section>
    <section class="tarjeta" id="ayuda-acceso"><div class="grupo-form-titulo"><?= icono('info') ?> Mesa de ayuda escolar</div><p class="nota">Si no recuerda su correo o tiene dificultades con su cuenta, acérquese al personal de biblioteca con su DNI. Para recuperar una contraseña, utilice el correo registrado.</p><a class="enlace-sec" href="<?= e(url('recuperar')) ?>">Recuperar acceso <?= icono('arrow') ?></a></section>
  </aside>
</div>
<?php pagina_fin();
