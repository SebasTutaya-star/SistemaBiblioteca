<?php
// RF-03: el Administrador gestiona las cuentas (registro, actualización, activación y desactivación).
if (es_post()) {
    csrf_verificar();
    $id = (int)p('id');
    $accion = p('accion');
    $obj = fila('SELECT * FROM usuarios WHERE id = ?', [$id]);
    if (!$obj) { flash('error', 'Usuario no encontrado.'); redirigir('usuarios'); }
    if ($accion === 'estado') {
        if ($id === (int)usuario()['id']) {
            flash('error', 'No puede desactivar su propia cuenta.');
        } else {
            $nuevo = $obj['activo'] ? 0 : 1;
            q('UPDATE usuarios SET activo = ? WHERE id = ?', [$nuevo, $id]);
            flash('ok', 'Cuenta de ' . nombre_completo($obj) . ($nuevo ? ' activada.' : ' desactivada. Su historial se conserva.'));
        }
    } elseif ($accion === 'restablecer') {
        q('UPDATE usuarios SET clave_hash = ?, debe_cambiar_clave = 1, intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id = ?',
          [password_hash($obj['dni'], PASSWORD_DEFAULT), $id]);
        flash('ok', 'La contraseña de ' . nombre_completo($obj) . ' volvió a ser su DNI. Deberá cambiarla al ingresar.');
    }
    redirigir('usuarios', array_filter(['q' => g('q'), 'rol' => g('rol')]));
}

$texto = g('q');
$rol = g('rol');
$estado = g('estado');
$where = ['1=1'];
$params = [];
if ($texto !== '') {
    $like = "%$texto%";
    $where[] = "(dni LIKE ? OR correo LIKE ? OR CONCAT(nombres,' ',apellidos) LIKE ?)";
    array_push($params, $like, $like, $like);
}
if (isset(ROLES[$rol])) { $where[] = 'rol = ?'; $params[] = $rol; }
if ($estado === '1' || $estado === '0') { $where[] = 'activo = ?'; $params[] = (int)$estado; }
$sql = 'FROM usuarios WHERE ' . implode(' AND ', $where);
$total = (int)valor("SELECT COUNT(*) $sql", $params);
[$offset, $limite, $pag] = paginacion(30);
$lista = filas("SELECT * $sql ORDER BY FIELD(rol,'admin','bibliotecaria','auxiliar','lector'), apellidos LIMIT $limite OFFSET $offset", $params);

pagina_inicio('Usuarios', 'usuarios');
encabezado('Usuarios', $total . ' cuenta(s)',
    '<a class="btn" href="' . e(url('padron')) . '">Importar padrón</a> <a class="btn btn-primario" href="' . e(url('usuario_form')) . '">+ Nuevo usuario</a>');
?>
<form method="get" class="buscador tarjeta" action="index.php">
  <input type="hidden" name="p" value="usuarios">
  <div class="fila-busqueda">
    <input type="search" name="q" value="<?= e($texto) ?>" placeholder="DNI, nombre o correo…">
    <select name="rol"><option value="">Todos los roles</option><?php foreach (ROLES as $k => $v): ?><option value="<?= $k ?>" <?= $k === $rol ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select>
    <select name="estado"><option value="">Activos e inactivos</option><option value="1" <?= $estado === '1' ? 'selected' : '' ?>>Solo activos</option><option value="0" <?= $estado === '0' ? 'selected' : '' ?>>Solo inactivos</option></select>
    <button class="btn btn-primario">Buscar</button>
  </div>
</form>
<div class="tabla-envoltura"><table class="tabla">
  <thead><tr><th>Nombre</th><th>DNI</th><th>Correo</th><th>Rol</th><th>Grado</th><th>Estado</th><th>Último acceso</th><th></th></tr></thead>
  <tbody><?php foreach ($lista as $r): ?>
    <tr class="<?= $r['activo'] ? '' : 'inactivo' ?>">
      <td><div class="material-celda"><span class="avatar"><?= e(mb_substr($r['nombres'],0,1) . mb_substr($r['apellidos'],0,1)) ?></span><strong><?= e($r['apellidos'] . ', ' . $r['nombres']) ?></strong></div></td>
      <td><?= e($r['dni']) ?></td>
      <td><small><?= e($r['correo']) ?></small></td>
      <td><?= e(ROLES[$r['rol']]) ?><?= $r['tipo_lector'] ? '<br><small>' . e(ucfirst($r['tipo_lector'])) . '</small>' : '' ?></td>
      <td><?= e(trim($r['grado'] . ' ' . $r['seccion'])) ?: '—' ?></td>
      <td><?= $r['activo'] ? '<span class="estado est-verde">Activo</span>' : '<span class="estado est-gris">Inactivo</span>' ?></td>
      <td><small><?= fecha_es($r['ultimo_acceso'], true) ?></small></td>
      <td class="acciones-fila">
        <a href="<?= e(url('usuario_form', ['id' => $r['id']])) ?>">Editar</a>
        <form method="post" action="<?= e(url('usuarios', array_filter(['q' => $texto, 'rol' => $rol]))) ?>" class="en-linea">
          <?= csrf_campo() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <?php if ((int)$r['id'] !== (int)usuario()['id']): ?>
            <button class="enlace" name="accion" value="estado" data-confirmar="<?= $r['activo'] ? '¿Desactivar esta cuenta?' : '¿Activar esta cuenta?' ?>"><?= $r['activo'] ? 'Desactivar' : 'Activar' ?></button>
          <?php endif; ?>
          <button class="enlace" name="accion" value="restablecer" data-confirmar="¿Restablecer la contraseña al DNI?">Clave = DNI</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?></tbody>
</table></div>
<?= enlaces_paginacion($total, $limite, $pag, 'usuarios', array_filter(['q' => $texto, 'rol' => $rol, 'estado' => $estado])) ?>
<?php pagina_fin();
