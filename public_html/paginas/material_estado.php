<?php
// RF-05 (inactivar conservando historial), RF-07 (cantidades), RF-08 (estados por ejemplar: disponible, prestado, deteriorado, perdido).
$id = (int)(g('id') ?: p('id'));
$m = material_por_id($id);
if (!$m) { flash('error', 'Material no encontrado.'); redirigir('catalogo'); }

$acciones = [
    'deterioro'  => ['Marcar ejemplares como deteriorados', 'Se retiran de los disponibles (no se prestan).'],
    'reparado'   => ['Ejemplares deteriorados recuperados', 'Vuelven a estar disponibles.'],
    'baja'       => ['Dar de baja ejemplares deteriorados', 'Se descuentan del total; el registro conserva el historial.'],
    'perdida'    => ['Registrar ejemplares perdidos (fuera de préstamo)', 'Ej.: faltante detectado en inventario.'],
    'reposicion' => ['Ejemplares perdidos repuestos o encontrados', 'Vuelven a estar disponibles.'],
];

if (es_post()) {
    csrf_verificar();
    $accion = p('accion');
    $obs = p('observacion');
    $pdo = db();
    $pdo->beginTransaction();
    // Bloqueo de la fila para evitar inconsistencias si dos personas operan a la vez (RNF-07)
    q('SELECT id FROM materiales WHERE id = ? FOR UPDATE', [$id]);
    $m = material_por_id($id);
    $error = null;

    if ($accion === 'inactivar' || $accion === 'activar') {
        if ($accion === 'inactivar' && $obs === '') $error = 'Indique el motivo de la inactivación.';
        else {
            q('UPDATE materiales SET activo = ? WHERE id = ?', [$accion === 'activar' ? 1 : 0, $id]);
            registrar_movimiento($id, $accion, 0, $obs);
        }
    } elseif (isset($acciones[$accion])) {
        $n = (int)p('cantidad');
        $disp = (int)$m['disponibles'];
        $det = (int)$m['cantidad_deteriorada'];
        $per = (int)$m['cantidad_perdida'];
        if ($n < 1) $error = 'Indique una cantidad mayor que cero.';
        elseif (in_array($accion, ['deterioro', 'perdida'], true) && $n > $disp) $error = "Solo hay $disp ejemplar(es) disponible(s) en la biblioteca. Los prestados se registran desde la devolución.";
        elseif (in_array($accion, ['reparado', 'baja'], true) && $n > $det) $error = "Solo hay $det ejemplar(es) deteriorado(s).";
        elseif ($accion === 'reposicion' && $n > $per) $error = "Solo hay $per ejemplar(es) perdido(s).";
        elseif ($accion === 'baja' && $n >= (int)$m['cantidad_total']) $error = 'No puede dar de baja todos los ejemplares; si el material ya no se usará, inactívelo.';
        else {
            $sql = [
                'deterioro'  => 'cantidad_deteriorada = cantidad_deteriorada + ?',
                'reparado'   => 'cantidad_deteriorada = cantidad_deteriorada - ?',
                'baja'       => 'cantidad_deteriorada = cantidad_deteriorada - ?, cantidad_total = cantidad_total - ?',
                'perdida'    => 'cantidad_perdida = cantidad_perdida + ?',
                'reposicion' => 'cantidad_perdida = cantidad_perdida - ?',
            ][$accion];
            q("UPDATE materiales SET $sql WHERE id = ?", $accion === 'baja' ? [$n, $n, $id] : [$n, $id]);
            $tipoMov = $accion === 'baja' ? 'ajuste_cantidad' : $accion;
            registrar_movimiento($id, $tipoMov, $accion === 'baja' ? -$n : $n, ($accion === 'baja' ? 'Baja por deterioro. ' : '') . $obs);
        }
    } else {
        $error = 'Acción no válida.';
    }

    if ($error) {
        $pdo->rollBack();
        flash('error', $error);
    } else {
        $pdo->commit();
        flash('ok', 'Cambio registrado.');
    }
    redirigir('material_estado', ['id' => $id]);
}

pagina_inicio('Estados y cantidades', 'catalogo');
encabezado('Estados y cantidades', $m['codigo'] . ' · ' . $m['titulo'],
    '<a class="btn" href="' . e(url('material_ver', ['id' => $id])) . '">‹ Volver a la ficha</a>');
?>
<div class="cantidades tarjeta">
  <div><span><?= (int)$m['cantidad_total'] ?></span>Total</div>
  <div class="c-verde"><span><?= (int)$m['disponibles'] ?></span>Disponibles</div>
  <div class="c-azul"><span><?= (int)$m['prestados'] ?></span>Prestados</div>
  <div class="c-ambar"><span><?= (int)$m['cantidad_deteriorada'] ?></span>Deteriorados</div>
  <div class="c-rojo"><span><?= (int)$m['cantidad_perdida'] ?></span>Perdidos</div>
</div>
<p class="nota">Los ejemplares prestados se actualizan solos al registrar préstamos y devoluciones. Use esta página para los cambios que ocurren dentro de la biblioteca.</p>

<div class="rejilla-2">
  <form method="post" class="tarjeta" action="<?= e(url('material_estado')) ?>">
    <?= csrf_campo() ?><input type="hidden" name="id" value="<?= $id ?>">
    <h2>Actualizar estado de ejemplares</h2>
    <label>Cambio
      <select name="accion" required>
        <?php foreach ($acciones as $k => [$t, $ayuda]): ?><option value="<?= $k ?>"><?= e($t) ?> — <?= e($ayuda) ?></option><?php endforeach; ?>
      </select>
    </label>
    <label>Cantidad de ejemplares<input type="number" name="cantidad" min="1" value="1" required></label>
    <label>Observación<input name="observacion" maxlength="200" placeholder="Ej.: hojas arrancadas, humedad…"></label>
    <button class="btn btn-primario">Registrar cambio</button>
  </form>

  <form method="post" class="tarjeta" action="<?= e(url('material_estado')) ?>">
    <?= csrf_campo() ?><input type="hidden" name="id" value="<?= $id ?>">
    <h2>Estado del material</h2>
    <?php if ($m['activo']): ?>
      <p>El material está <strong>activo</strong>. Si ya no se utilizará (por ejemplo, todos los ejemplares están inservibles), puede inactivarlo. No se borra: su historial de préstamos se conserva y deja de aparecer para los lectores.</p>
      <?php if ($m['prestados'] > 0): ?><div class="aviso aviso-info">Tiene <?= (int)$m['prestados'] ?> ejemplar(es) prestado(s); podrán devolverse normalmente.</div><?php endif; ?>
      <input type="hidden" name="accion" value="inactivar">
      <label>Motivo *<input name="observacion" required maxlength="200"></label>
      <button class="btn btn-peligro" data-confirmar="¿Inactivar este material?">Inactivar material</button>
    <?php else: ?>
      <p>El material está <strong>inactivo</strong> y no puede prestarse.</p>
      <input type="hidden" name="accion" value="activar">
      <label>Observación<input name="observacion" maxlength="200"></label>
      <button class="btn btn-primario">Reactivar material</button>
    <?php endif; ?>
  </form>
</div>
<?php pagina_fin();
