<?php
// RF-01: Recuperación de contraseña mediante el correo registrado.
$enviado = false;
if (es_post()) {
    csrf_verificar();
    $correo = mb_strtolower(p('correo'));
    $u = fila('SELECT id, nombres, correo FROM usuarios WHERE correo = ? AND activo = 1', [$correo]);
    // Límite simple: una solicitud por minuto por sesión
    $ultimo = $_SESSION['ultima_recuperacion'] ?? 0;
    if ($u && time() - $ultimo > 60) {
        $_SESSION['ultima_recuperacion'] = time();
        $token = bin2hex(random_bytes(32));
        q('UPDATE recuperacion_clave SET usado = 1 WHERE usuario_id = ? AND usado = 0', [$u['id']]);
        q('INSERT INTO recuperacion_clave (usuario_id, token_hash, expira_en) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))',
          [$u['id'], hash('sha256', $token)]);
        $enlace = rtrim($GLOBALS['CONFIG']['url_base'], '/') . '/index.php?p=restablecer&token=' . $token;
        $cuerpo = "Hola {$u['nombres']}:\n\nRecibimos una solicitud para restablecer su contraseña de la Biblioteca I.E.P. San Juan.\n"
                . "Abra este enlace (válido por 1 hora):\n\n$enlace\n\n"
                . "Si usted no lo solicitó, ignore este mensaje; su contraseña no cambiará.";
        $ok = enviar_correo($u['correo'], 'Restablecer contraseña - Biblioteca San Juan', $cuerpo);
        if (!$ok) error_log('No se pudo enviar el correo de recuperación a ' . $u['correo']);
    }
    $enviado = true; // mensaje genérico: no revela si el correo existe
}
pagina_inicio('Recuperar contraseña');
?>
<div class="auth-recuperar">
  <div class="recuperar-identidad"><img src="assets/img/insignia-san-juan.png?v=institucion-1" alt="Escudo oficial I.E. San Juan"><h1>Recuperar contraseña</h1><p class="sub">Ingresa tu correo institucional escolar para recibir un enlace seguro de restauración de acceso.</p></div>
  <section class="tarjeta">
    <div class="pasos" aria-label="Pasos de recuperación"><span class="paso activo">1. Correo institucional</span><span class="paso <?= $enviado ? 'activo' : '' ?>">2. Enlace por correo</span><span class="paso">3. Nueva contraseña</span></div>
    <?php if ($enviado): ?>
      <div class="aviso aviso-ok" role="status"><?= icono('check') ?> Si el correo está registrado y activo, recibirá un enlace para crear una nueva contraseña. Revise también la carpeta de spam.</div>
    <?php else: ?>
      <div class="tabs" aria-label="Tipo de cuenta"><button type="button" class="activo" data-rol-acceso="estudiante" aria-pressed="true"><?= icono('school') ?> Estudiante</button><button type="button" data-rol-acceso="personal" aria-pressed="false"><?= icono('person') ?> Docente o personal</button></div>
      <form method="post" action="<?= e(url('recuperar')) ?>">
        <?= csrf_campo() ?>
        <label>Correo institucional<div class="campo-icono"><?= icono('mail') ?><input type="email" name="correo" required autofocus autocomplete="email" placeholder="estudiante@iesanjuan.edu.pe"></div></label>
        <button class="btn btn-primario btn-bloque"><?= icono('mail') ?> Enviar enlace de recuperación</button>
      </form>
    <?php endif; ?>
    <div class="login-nota" id="ayuda-acceso"><?= icono('info') ?><div><strong>Orientación al lector</strong><p id="nota-acceso">Revise su carpeta de correo no deseado. Si no recuerda su correo registrado, acérquese al mostrador de biblioteca con su DNI.</p></div></div>
    <a class="enlace-sec" href="<?= e(url('login')) ?>"><?= icono('back') ?> Volver al inicio de sesión</a>
  </section>
</div>
<?php pagina_fin();
