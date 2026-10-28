<?php
// RF-01: Crear nueva contraseña desde el enlace enviado al correo.
$token = g('token') ?: p('token');
$reg = null;
if (preg_match('/^[a-f0-9]{64}$/', $token)) {
    $reg = fila('SELECT r.id, r.usuario_id, u.dni FROM recuperacion_clave r JOIN usuarios u ON u.id = r.usuario_id
                 WHERE r.token_hash = ? AND r.usado = 0 AND r.expira_en > NOW() AND u.activo = 1', [hash('sha256', $token)]);
}
$error = null;
if ($reg && es_post()) {
    csrf_verificar();
    $clave = (string)($_POST['clave'] ?? '');
    $error = validar_clave_nueva($clave, (string)($_POST['clave2'] ?? ''), ['dni' => $reg['dni']]);
    if (!$error) {
        q('UPDATE usuarios SET clave_hash = ?, debe_cambiar_clave = 0, intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id = ?',
          [password_hash($clave, PASSWORD_DEFAULT), $reg['usuario_id']]);
        q('UPDATE recuperacion_clave SET usado = 1 WHERE id = ?', [$reg['id']]);
        flash('ok', 'Contraseña actualizada. Ya puede iniciar sesión.');
        redirigir('login');
    }
}
pagina_inicio('Nueva contraseña');
?>
<div class="auth-recuperar"><div class="recuperar-identidad"><img src="assets/img/insignia-san-juan.png" alt="Insignia I.E. San Juan"><p class="sobrelinea">Biblioteca escolar · Acceso seguro</p></div><div class="tarjeta" id="ayuda-acceso">
  <h1>Nueva contraseña</h1>
  <?php if (!$reg): ?>
    <div class="aviso aviso-error">El enlace no es válido o ya expiró. Solicite uno nuevo.</div>
    <a class="enlace-sec" href="<?= e(url('recuperar')) ?>">Solicitar otro enlace</a>
  <?php else: ?>
    <?php if ($error): ?><div class="aviso aviso-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" action="<?= e(url('restablecer')) ?>">
      <?= csrf_campo() ?>
      <input type="hidden" name="token" value="<?= e($token) ?>">
      <label>Nueva contraseña <input type="password" name="clave" required minlength="8" autocomplete="new-password"></label>
      <label>Repita la contraseña <input type="password" name="clave2" required minlength="8" autocomplete="new-password"></label>
      <p class="nota">Mínimo 8 caracteres, con letras y números.</p>
      <button class="btn btn-primario btn-bloque">Guardar contraseña</button>
    </form>
  <?php endif; ?>
<a class="enlace-sec" href="<?= e(url('login')) ?>">Volver al inicio de sesión</a></div>
</div>
<?php pagina_fin();
