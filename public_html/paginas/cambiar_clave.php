<?php
// RF-01: Cambio de contraseña por el propio usuario.
$u = usuario();
$error = null;
if (es_post()) {
    csrf_verificar();
    $actual = (string)($_POST['actual'] ?? '');
    $nueva  = (string)($_POST['clave'] ?? '');
    $hash = valor('SELECT clave_hash FROM usuarios WHERE id = ?', [$u['id']]);
    if (!password_verify($actual, $hash)) {
        $error = 'La contraseña actual no es correcta.';
    } elseif ($actual === $nueva) {
        $error = 'La nueva contraseña debe ser distinta de la actual.';
    } else {
        $error = validar_clave_nueva($nueva, (string)($_POST['clave2'] ?? ''), $u);
    }
    if (!$error) {
        q('UPDATE usuarios SET clave_hash = ?, debe_cambiar_clave = 0 WHERE id = ?', [password_hash($nueva, PASSWORD_DEFAULT), $u['id']]);
        session_regenerate_id(true);
        flash('ok', 'Su contraseña fue actualizada.');
        redirigir('inicio');
    }
}
pagina_inicio('Cambiar contraseña');
encabezado('Mi cuenta', 'Datos de tu cuenta y seguridad de acceso.');
?>
<?php if ($u['debe_cambiar_clave']): ?>
  <div class="aviso aviso-info">Por seguridad, cambie su contraseña inicial antes de continuar.</div>
<?php endif; ?>
<?php if ($error): ?><div class="aviso aviso-error"><?= e($error) ?></div><?php endif; ?>
<div class="cuenta-panel"><section class="perfil-carnet"><small>Cuenta de biblioteca escolar</small><h2><?= e(nombre_completo($u)) ?></h2><span><?= e(ROLES[$u['rol']]) ?><?= $u['tipo_lector'] ? ' · ' . e(ucfirst($u['tipo_lector'])) : '' ?></span><div class="perfil-datos"><div><small>DNI</small><strong><?= e($u['dni']) ?></strong></div><div><small>Correo institucional</small><strong><?= e($u['correo']) ?></strong></div></div></section>
<form method="post" class="tarjeta" action="<?= e(url('cambiar_clave')) ?>">
  <?= csrf_campo() ?><div class="grupo-form-titulo"><?= icono('key') ?> Cambiar contraseña</div>
  <label>Contraseña actual <input type="password" name="actual" required autocomplete="current-password"></label>
  <label>Nueva contraseña <input type="password" name="clave" required minlength="8" autocomplete="new-password"></label>
  <label>Repita la nueva contraseña <input type="password" name="clave2" required minlength="8" autocomplete="new-password"></label>
  <p class="nota">Mínimo 8 caracteres, con letras y números. No use su DNI.</p>
  <button class="btn btn-primario">Guardar</button>
</form></div>
<?php pagina_fin();
