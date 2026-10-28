<?php
// RF-16: registrar devolución actualizando la disponibilidad. RF-17: responsable, fecha y hora.
// También permite registrar el libro como perdido (se solicitará reposición del mismo libro; no se aceptan pagos).
$id = (int)(g('id') ?: p('id'));
$pr = prestamo_detalle($id);
if (!$pr || $pr['estado'] !== 'activo') {
    flash('error', 'El préstamo no existe o ya no está activo.');
    redirigir('prestamos');
}

if (es_post()) {
    csrf_verificar();
    $resultado = p('resultado');
    $obs = mb_substr(p('observacion'), 0, 255);
    if (!in_array($resultado, ['bueno', 'deteriorado', 'perdido'], true)) {
        flash('error', 'Seleccione el estado en que se recibe el material.');
        redirigir('devolucion', ['id' => $id]);
    }
    $pdo = db();
    $pdo->beginTransaction();
    $actual = fila('SELECT estado FROM prestamos WHERE id = ? FOR UPDATE', [$id]);
    if ($actual['estado'] !== 'activo') {
        $pdo->rollBack();
        flash('error', 'Este préstamo ya fue cerrado por otro usuario.');
        redirigir('prestamos');
    }
    $uid = usuario()['id'];
    $obsTotal = trim(($pr['observacion'] ? $pr['observacion'] . ' | ' : '') . $obs, ' |') ?: null;

    if ($resultado === 'perdido') {
        q("UPDATE prestamos SET estado = 'perdido', fecha_devolucion = NOW(), recibido_por = ?, observacion = ? WHERE id = ?",
          [$uid, $obsTotal, $id]);
        q('UPDATE materiales SET cantidad_perdida = cantidad_perdida + 1 WHERE id = ?', [$pr['material_id']]);
        registrar_movimiento((int)$pr['material_id'], 'perdida', 1, 'Perdido por ' . nombre_completo($pr) . " (préstamo #$id). $obs");
        $msg = 'Se registró la pérdida. El lector queda pendiente de reponer el mismo libro.';
    } else {
        q("UPDATE prestamos SET estado = 'devuelto', fecha_devolucion = NOW(), recibido_por = ?, condicion_devolucion = ?, observacion = ? WHERE id = ?",
          [$uid, $resultado, $obsTotal, $id]);
        if ($resultado === 'deteriorado') {
            q('UPDATE materiales SET cantidad_deteriorada = cantidad_deteriorada + 1 WHERE id = ?', [$pr['material_id']]);
            registrar_movimiento((int)$pr['material_id'], 'deterioro', 1, 'Devuelto deteriorado por ' . nombre_completo($pr) . " (préstamo #$id). $obs");
        }
        $msg = 'Devolución registrada. ' . ($resultado === 'bueno' ? 'El ejemplar vuelve a estar disponible.' : 'El ejemplar quedó como deteriorado.');
    }
    $pdo->commit();
    flash('ok', $msg);
    redirigir('prestamos');
}

$retraso = dias_entre($pr['fecha_vencimiento'], hoy());
pagina_inicio('Registrar devolución', 'prestamos');
encabezado('Registrar devolución', $pr['codigo'] . ' · ' . $pr['titulo'], '<a class="btn" href="' . e(url('prestamos')) . '">‹ Préstamos</a>');
?>
<div class="rejilla-2">
  <section class="tarjeta">
    <div class="grupo-form-titulo"><?= icono('book') ?> Datos del préstamo</div><div class="material-celda" style="margin-bottom:20px"><?= ui_portada($pr, 'portada-mini') ?><strong><?= e($pr['titulo']) ?></strong></div>
    <dl class="datos-grandes">
      <dt>Lector</dt><dd><?= e(nombre_completo($pr)) ?> · DNI <?= e($pr['dni']) ?></dd>
      <dt>Tipo</dt><dd><?= e(ucfirst((string)$pr['tipo_lector'])) ?><?= $pr['grado'] ? ' · ' . e($pr['grado'] . ' ' . $pr['seccion']) : '' ?></dd>
      <dt>Prestado el</dt><dd><?= fecha_es($pr['fecha_prestamo'], true) ?></dd>
      <dt>Devolver hasta</dt><dd><?= fecha_es($pr['fecha_vencimiento']) ?> <?= estado_prestamo_html($pr) ?></dd>
      <?php if ($retraso > 0): ?><dt>Retraso</dt><dd><strong><?= $retraso ?> día(s)</strong></dd><?php endif; ?>
    </dl>
  </section>
  <form method="post" class="tarjeta" action="<?= e(url('devolucion')) ?>">
    <?= csrf_campo() ?><input type="hidden" name="id" value="<?= $id ?>">
    <h2>¿En qué estado se recibe?</h2>
    <label class="opcion"><input type="radio" name="resultado" value="bueno" checked> <span><strong>En buen estado</strong><small>Vuelve a estar disponible.</small></span></label>
    <label class="opcion"><input type="radio" name="resultado" value="deteriorado"> <span><strong>Deteriorado</strong><small>Se recibe, pero no se volverá a prestar hasta repararse.</small></span></label>
    <label class="opcion"><input type="radio" name="resultado" value="perdido"> <span><strong>El lector perdió el libro</strong><small>Queda pendiente la reposición del mismo libro.</small></span></label>
    <label>Observación<input name="observacion" maxlength="200"></label>
    <button class="btn btn-primario">Registrar</button>
  </form>
</div>
<?php pagina_fin();
