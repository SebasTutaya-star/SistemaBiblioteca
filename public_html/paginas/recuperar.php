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
<div class="tarjeta angosta">
  <h1>Recuperar contraseña</h1>
  <?php if ($enviado): ?>
    <div class="aviso aviso-ok">Si el correo está registrado y activo, recibirá un enlace para crear una nueva contraseña. Revise también la carpeta de spam.</div>
  <?php else: ?>
    <p class="sub">Escriba su correo institucional. Le enviaremos un enlace para crear una nueva contraseña.</p>
    <form method="post" action="<?= e(url('recuperar')) ?>">
      <?= csrf_campo() ?>
      <label>Correo institucional <input type="email" name="correo" required autofocus></label>
      <button class="btn btn-primario btn-bloque">Enviar enlace</button>
    </form>
  <?php endif; ?>
  <a class="enlace-sec" href="<?= e(url('login')) ?>">‹ Volver al inicio de sesión</a>
</div>
<?php pagina_fin();
