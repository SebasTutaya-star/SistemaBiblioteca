<?php
// RF-13: gestión de reglas y condiciones de préstamo (tipos de material, plazos, límites).
$claves = [
    'max_prestamos_estudiante' => ['Máximo de libros simultáneos por estudiante', 'number'],
    'max_prestamos_docente'    => ['Máximo de libros simultáneos por docente (0 = sin límite)', 'number'],
    'bloquear_con_vencidos'    => ['No prestar a lectores con préstamos vencidos', 'bool'],
    'bloquear_con_perdidos'    => ['No prestar a lectores con libros perdidos sin reponer', 'bool'],
    'dias_alerta_proximo'      => ['Días de anticipación para alertas de vencimiento', 'number'],
    'fin_periodo_academico'    => ['Fecha de cierre para textos escolares', 'date'],
    'patron_codigo'            => ['Esquema obligatorio de códigos (expresión regular, opcional)', 'text'],
];

if (es_post()) {
    csrf_verificar();
    $accion = p('accion');
    if ($accion === 'config') {
        $error = null;
        foreach ($claves as $k => [$etq, $tipo]) {
            $v = $tipo === 'bool' ? (p($k) === '1' ? '1' : '0') : p($k);
            if ($tipo === 'number' && (!ctype_digit($v) || (int)$v > 365)) $error = "Valor no válido en «$etq».";
            if ($tipo === 'date' && !fecha_valida($v)) $error = "Fecha no válida en «$etq».";
            if ($k === 'patron_codigo' && $v !== '' && @preg_match('/' . str_replace('/', '\/', $v) . '/u', '') === false) $error = 'La expresión del esquema de códigos no es válida.';
            if ($error) break;
            $vals[$k] = $v;
        }
        if ($error) flash('error', $error);
        else {
            foreach ($vals as $k => $v) q('UPDATE configuracion SET valor = ? WHERE clave = ?', [$v, $k]);
            flash('ok', 'Reglas actualizadas.');
        }
    } elseif ($accion === 'tipo') {
        $id = (int)p('id');
        $nombre = p('nombre');
        $dias = (int)p('dias_prestamo');
        $fin = p('hasta_fin_periodo') === '1' ? 1 : 0;
        $activo = p('activo', '1') === '1' ? 1 : 0;
        if ($nombre === '' || $dias < 0 || $dias > 365 || (!$fin && $dias < 1)) flash('error', 'Indique un nombre y un plazo entre 1 y 365 días (o marque «hasta fin del periodo»).');
        elseif (valor('SELECT id FROM tipos_material WHERE nombre = ? AND id <> ?', [$nombre, $id])) flash('error', 'Ya existe un tipo con ese nombre.');
        else {
            if ($id) q('UPDATE tipos_material SET nombre=?, dias_prestamo=?, hasta_fin_periodo=?, activo=? WHERE id=?', [$nombre, $dias, $fin, $activo, $id]);
            else q('INSERT INTO tipos_material (nombre, dias_prestamo, hasta_fin_periodo) VALUES (?,?,?)', [$nombre, $dias, $fin]);
            flash('ok', 'Tipo de material guardado.');
        }
    }
    redirigir('reglas');
}
$tipos = filas('SELECT t.*, (SELECT COUNT(*) FROM materiales m WHERE m.tipo_material_id = t.id) AS usados FROM tipos_material t ORDER BY nombre');
pagina_inicio('Reglas de préstamo', 'reglas');
encabezado('Reglas de préstamo', 'Condiciones que el sistema verifica al registrar cada préstamo.');
?>
<div class="rejilla-2">
  <form method="post" class="tarjeta" action="<?= e(url('reglas')) ?>">
    <?= csrf_campo() ?><input type="hidden" name="accion" value="config">
    <h2>Condiciones generales</h2>
    <?php foreach ($claves as $k => [$etq, $tipo]): $v = (string)conf($k, ''); ?>
      <?php if ($tipo === 'bool'): ?>
        <label class="check"><input type="checkbox" name="<?= $k ?>" value="1" <?= $v === '1' ? 'checked' : '' ?>> <?= e($etq) ?></label>
      <?php else: ?>
        <label><?= e($etq) ?><input type="<?= $tipo ?>" name="<?= $k ?>" value="<?= e($v) ?>" <?= $tipo === 'number' ? 'min="0" max="365"' : '' ?> <?= $k === 'patron_codigo' ? 'placeholder="Ej.: ^[A-Z]{3}-[A-Z]{3}-\d{4}$  (vacío = libre)"' : '' ?>></label>
      <?php endif; ?>
    <?php endforeach; ?>
    <button class="btn btn-primario">Guardar condiciones</button>
  </form>
  <section class="tarjeta">
    <h2>Tipos de material y plazos</h2>
    <p class="nota">El plazo se propone al registrar el préstamo; el personal puede ajustarlo en cada caso.</p>
    <?php foreach ($tipos as $t): ?>
      <form method="post" class="tipo-fila" action="<?= e(url('reglas')) ?>">
        <?= csrf_campo() ?><input type="hidden" name="accion" value="tipo"><input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
        <input name="nombre" value="<?= e($t['nombre']) ?>" aria-label="Nombre" required>
        <label class="en-linea"><input type="number" name="dias_prestamo" value="<?= (int)$t['dias_prestamo'] ?>" min="0" max="365" aria-label="Días"> días</label>
        <label class="check"><input type="checkbox" name="hasta_fin_periodo" value="1" <?= $t['hasta_fin_periodo'] ? 'checked' : '' ?>> hasta fin del periodo</label>
        <select name="activo" aria-label="Estado"><option value="1" <?= $t['activo'] ? 'selected' : '' ?>>Activo</option><option value="0" <?= !$t['activo'] ? 'selected' : '' ?>>Inactivo</option></select>
        <button class="btn btn-chico">Guardar</button>
        <small><?= (int)$t['usados'] ?> material(es)</small>
      </form>
    <?php endforeach; ?>
    <h3>Nuevo tipo</h3>
    <form method="post" class="tipo-fila" action="<?= e(url('reglas')) ?>">
      <?= csrf_campo() ?><input type="hidden" name="accion" value="tipo"><input type="hidden" name="id" value="0">
      <input name="nombre" placeholder="Nombre" required>
      <label class="en-linea"><input type="number" name="dias_prestamo" value="5" min="0" max="365"> días</label>
      <label class="check"><input type="checkbox" name="hasta_fin_periodo" value="1"> hasta fin del periodo</label>
      <button class="btn btn-chico btn-primario">Agregar</button>
    </form>
  </section>
</div>
<?php pagina_fin();
