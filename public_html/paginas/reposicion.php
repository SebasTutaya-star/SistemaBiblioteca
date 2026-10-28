<?php
// Reposición de un libro perdido durante un préstamo: el lector entrega el mismo libro (no se aceptan pagos).
$id = (int)(g('id') ?: p('id'));
$pr = prestamo_detalle($id);
if (!$pr || $pr['estado'] !== 'perdido') {
    flash('error', 'El préstamo no está registrado como perdido.');
    redirigir('prestamos', ['filtro' => 'perdidos']);
}
if (es_post()) {
    csrf_verificar();
    $obs = mb_substr(p('observacion'), 0, 200);
    $pdo = db();
    $pdo->beginTransaction();
    $act = fila('SELECT estado FROM prestamos WHERE id = ? FOR UPDATE', [$id]);
    if ($act['estado'] === 'perdido') {
        q("UPDATE prestamos SET estado = 'repuesto', fecha_reposicion = NOW(), reposicion_por = ? WHERE id = ?", [usuario()['id'], $id]);
        q('UPDATE materiales SET cantidad_perdida = GREATEST(cantidad_perdida - 1, 0) WHERE id = ?', [$pr['material_id']]);
        registrar_movimiento((int)$pr['material_id'], 'reposicion', 1, 'Repuesto por ' . nombre_completo($pr) . " (préstamo #$id). $obs");
    }
    $pdo->commit();
    flash('ok', 'Reposición registrada. El ejemplar vuelve a estar disponible y el lector queda al día.');
    redirigir('prestamos', ['filtro' => 'perdidos']);
}
pagina_inicio('Registrar reposición', 'prestamos');
encabezado('Registrar reposición', $pr['codigo'] . ' · ' . $pr['titulo'], '<a class="btn" href="' . e(url('prestamos', ['filtro' => 'perdidos'])) . '">‹ Volver</a>');
?>
<form method="post" class="tarjeta angosta" action="<?= e(url('reposicion')) ?>">
  <?= csrf_campo() ?><input type="hidden" name="id" value="<?= $id ?>">
  <div class="grupo-form-titulo"><?= icono('book') ?> Reposición de material</div><div class="material-celda" style="margin-bottom:20px"><?= ui_portada($pr, 'portada-mini') ?><strong><?= e($pr['titulo']) ?></strong></div>
  <p><strong><?= e(nombre_completo($pr)) ?></strong> (DNI <?= e($pr['dni']) ?>) perdió este libro; la pérdida se registró el <?= fecha_es($pr['fecha_devolucion'], true) ?>.</p>
  <p>Confirme que el lector entregó <strong>el mismo libro</strong> en reposición.</p>
  <label>Observación<input name="observacion" maxlength="200" placeholder="Ej.: entregó ejemplar nuevo, misma edición"></label>
  <button class="btn btn-primario">Confirmar reposición</button>
</form>
<?php pagina_fin();
