<?php
// RF-15: renovación o ampliación del plazo con justificación del Lector (autoriza Bibliotecaria o Auxiliar).
$id = (int)(g('id') ?: p('id'));
$pr = prestamo_detalle($id);
if (!$pr || $pr['estado'] !== 'activo') {
    flash('error', 'Solo se pueden renovar préstamos activos.');
    redirigir('prestamos');
}
$error = null;
$nueva = p('fecha_nueva');
$just = p('justificacion');

if (es_post()) {
    csrf_verificar();
    $minimo = max(hoy(), date('Y-m-d', strtotime($pr['fecha_vencimiento'] . ' +1 day')));
    if (!fecha_valida($nueva)) $error = 'Indique la nueva fecha de devolución.';
    elseif ($nueva < $minimo) $error = 'La nueva fecha debe ser posterior a la fecha de devolución actual y no anterior a hoy.';
    elseif (mb_strlen($just) < 5) $error = 'Escriba la justificación presentada por el lector.';
    if (!$error) {
        $pdo = db();
        $pdo->beginTransaction();
        q('INSERT INTO renovaciones (prestamo_id, fecha_anterior, fecha_nueva, justificacion, autorizado_por) VALUES (?,?,?,?,?)',
          [$id, $pr['fecha_vencimiento'], $nueva, mb_substr($just, 0, 255), usuario()['id']]);
        q('UPDATE prestamos SET fecha_vencimiento = ?, renovaciones = renovaciones + 1 WHERE id = ?', [$nueva, $id]);
        $pdo->commit();
        flash('ok', 'Plazo ampliado hasta el ' . fecha_es($nueva) . '.');
        redirigir('prestamos');
    }
}
if ($nueva === '') {
    $m = material_por_id((int)$pr['material_id']);
    $base = max(hoy(), $pr['fecha_vencimiento']);
    $dias = max(1, dias_entre(substr($pr['fecha_prestamo'], 0, 10), $pr['fecha_vencimiento']));
    $nueva = date('Y-m-d', strtotime("$base +$dias days"));
}
$previas = filas('SELECT r.*, u.nombres, u.apellidos FROM renovaciones r JOIN usuarios u ON u.id = r.autorizado_por WHERE prestamo_id = ? ORDER BY fecha', [$id]);

pagina_inicio('Renovar préstamo', 'prestamos');
encabezado('Renovar o ampliar plazo', $pr['titulo'] . ' · ' . nombre_completo($pr), '<a class="btn" href="' . e(url('prestamos')) . '">‹ Préstamos</a>');
if ($error) echo '<div class="aviso aviso-error">' . e($error) . '</div>';
?>
<div class="rejilla-2">
  <form method="post" class="tarjeta" action="<?= e(url('renovacion')) ?>">
    <?= csrf_campo() ?><input type="hidden" name="id" value="<?= $id ?>">
    <div class="grupo-form-titulo"><?= icono('clock') ?> Renovar plazo de lectura</div><p>Fecha de devolución actual: <strong><?= fecha_es($pr['fecha_vencimiento']) ?></strong> <?= estado_prestamo_html($pr) ?></p>
    <label>Nueva fecha de devolución *<input type="date" name="fecha_nueva" value="<?= e($nueva) ?>" min="<?= hoy() ?>" required></label>
    <label>Justificación presentada por el lector *<textarea name="justificacion" rows="3" maxlength="255" required><?= e($just) ?></textarea></label>
    <button class="btn btn-primario">Autorizar ampliación</button>
    <p class="nota">Quedará registrado que usted autorizó la ampliación, con fecha y hora.</p>
  </form>
  <section class="tarjeta">
    <h2>Renovaciones anteriores</h2>
    <?php if (!$previas): ?><p class="vacio">Este préstamo no tiene renovaciones.</p><?php else: ?>
      <ul class="lista-simple"><?php foreach ($previas as $r): ?>
        <li><?= fecha_es($r['fecha'], true) ?> — de <?= fecha_es($r['fecha_anterior']) ?> a <?= fecha_es($r['fecha_nueva']) ?>.
          <em><?= e($r['justificacion']) ?></em> (<?= e(nombre_completo($r)) ?>)</li>
      <?php endforeach; ?></ul>
    <?php endif; ?>
  </section>
</div>
<?php pagina_fin();
