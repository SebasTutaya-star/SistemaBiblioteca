<?php
// RF-02 (asignación de rol) y RF-03 (registro y actualización de cuentas, incluidos ingresos durante el año).
$id = (int)(g('id') ?: p('id'));
$obj = $id ? fila('SELECT * FROM usuarios WHERE id = ?', [$id]) : null;
if ($id && !$obj) { flash('error', 'Usuario no encontrado.'); redirigir('usuarios'); }

$campos = ['dni', 'nombres', 'apellidos', 'correo', 'rol', 'tipo_lector', 'grado', 'seccion'];
$d = $obj ? array_intersect_key($obj, array_flip($campos)) : array_fill_keys($campos, '') + ['rol' => 'lector', 'tipo_lector' => 'estudiante'];
$errores = [];

if (es_post()) {
    csrf_verificar();
    foreach ($campos as $c) $d[$c] = p($c);
    $d['correo'] = mb_strtolower($d['correo']);
    if (!preg_match('/^\d{8,12}$/', $d['dni'])) $errores['dni'] = 'El DNI debe tener entre 8 y 12 dígitos.';
    elseif (valor('SELECT id FROM usuarios WHERE dni = ? AND id <> ?', [$d['dni'], $id])) $errores['dni'] = 'Ya existe un usuario con ese DNI.';
    if ($d['nombres'] === '') $errores['nombres'] = 'Obligatorio.';
    if ($d['apellidos'] === '') $errores['apellidos'] = 'Obligatorio.';
    if (!filter_var($d['correo'], FILTER_VALIDATE_EMAIL)) $errores['correo'] = 'Correo no válido.';
    elseif (valor('SELECT id FROM usuarios WHERE correo = ? AND id <> ?', [$d['correo'], $id])) $errores['correo'] = 'Ese correo ya está registrado.';
    if (!isset(ROLES[$d['rol']])) $errores['rol'] = 'Rol no válido.';
    if ($obj && $id === (int)usuario()['id'] && $d['rol'] !== 'admin') $errores['rol'] = 'No puede quitarse a sí mismo el rol de Administrador.';
    if ($d['rol'] === 'lector') {
        if (!in_array($d['tipo_lector'], ['estudiante', 'docente'], true)) $errores['tipo_lector'] = 'Indique si es estudiante o docente.';
    } else {
        $d['tipo_lector'] = null;
        $d['grado'] = $d['seccion'] = '';
    }
    if ($d['rol'] === 'lector' && $d['tipo_lector'] === 'docente') $d['grado'] = $d['seccion'] = '';

    if (!$errores) {
        $vals = [$d['dni'], $d['nombres'], $d['apellidos'], $d['correo'], $d['rol'], $d['tipo_lector'], $d['grado'] ?: null, $d['seccion'] ?: null];
        if ($obj) {
            q('UPDATE usuarios SET dni=?, nombres=?, apellidos=?, correo=?, rol=?, tipo_lector=?, grado=?, seccion=? WHERE id=?', array_merge($vals, [$id]));
            flash('ok', 'Datos actualizados.');
        } else {
            q('INSERT INTO usuarios (dni, nombres, apellidos, correo, rol, tipo_lector, grado, seccion, periodo, clave_hash, debe_cambiar_clave)
               VALUES (?,?,?,?,?,?,?,?,?,?,1)', array_merge($vals, [conf('periodo_actual'), password_hash($d['dni'], PASSWORD_DEFAULT)]));
            flash('ok', 'Usuario registrado. Su contraseña inicial es su DNI; deberá cambiarla al ingresar.');
        }
        redirigir('usuarios');
    }
}
$err = fn($c) => isset($errores[$c]) ? '<span class="error-campo">' . e($errores[$c]) . '</span>' : '';
pagina_inicio($obj ? 'Editar usuario' : 'Nuevo usuario', 'usuarios');
encabezado($obj ? 'Editar usuario' : 'Registrar usuario', $obj ? nombre_completo($obj) : 'Para ingresos durante el año: traslados, incorporaciones u otros casos.');
?>
<form method="post" class="tarjeta formulario" action="<?= e(url('usuario_form')) ?>" id="form-usuario">
  <?= csrf_campo() ?><input type="hidden" name="id" value="<?= $id ?>">
  <div class="grupo-form-titulo"><?= icono('users') ?> Identidad y perfil del usuario</div>
  <div class="rejilla-form">
    <label>DNI *<input name="dni" value="<?= e($d['dni']) ?>" inputmode="numeric" maxlength="12" required><?= $err('dni') ?></label>
    <label>Correo institucional *<input type="email" name="correo" value="<?= e($d['correo']) ?>" required><?= $err('correo') ?></label>
    <label>Nombres *<input name="nombres" value="<?= e($d['nombres']) ?>" required maxlength="100"><?= $err('nombres') ?></label>
    <label>Apellidos *<input name="apellidos" value="<?= e($d['apellidos']) ?>" required maxlength="100"><?= $err('apellidos') ?></label>
    <label>Rol *
      <select name="rol" id="f-rol"><?php foreach (ROLES as $k => $v): ?><option value="<?= $k ?>" <?= $k === $d['rol'] ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select><?= $err('rol') ?>
    </label>
    <label class="solo-lector">Tipo de lector
      <select name="tipo_lector" id="f-tipo"><option value="estudiante" <?= $d['tipo_lector'] === 'estudiante' ? 'selected' : '' ?>>Estudiante</option><option value="docente" <?= $d['tipo_lector'] === 'docente' ? 'selected' : '' ?>>Docente</option></select><?= $err('tipo_lector') ?>
    </label>
    <label class="solo-estudiante">Grado<input name="grado" value="<?= e($d['grado']) ?>" maxlength="20" placeholder="Ej.: 4to"></label>
    <label class="solo-estudiante">Sección<input name="seccion" value="<?= e($d['seccion']) ?>" maxlength="10" placeholder="Ej.: A"></label>
  </div>
  <?php if (!$obj): ?><p class="nota">La contraseña inicial será el DNI y se pedirá cambiarla en el primer ingreso.</p><?php endif; ?>
  <div class="botonera"><a class="btn" href="<?= e(url('usuarios')) ?>">Cancelar</a><button class="btn btn-primario">Guardar</button></div>
</form>
<?php pagina_fin();
