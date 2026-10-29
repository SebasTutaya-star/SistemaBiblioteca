<?php
// RF-11 (registrar préstamo), RF-12 (verificaciones), RF-13 (reglas), RF-14 (advertencia de único ejemplar), RF-17 (responsable y fecha/hora).
$lectorId   = (int)(p('lector_id') ?: g('lector'));
$materialId = (int)(p('material_id') ?: g('material'));
$vence      = p('fecha_vencimiento');
$obs        = p('observacion');

$lector   = $lectorId ? fila('SELECT * FROM usuarios WHERE id = ?', [$lectorId]) : null;
$material = $materialId ? material_por_id($materialId) : null;
if ($material && $vence === '') $vence = vencimiento_sugerido($material);

$resultado = ['errores' => [], 'advertencias' => []];
$pedirConfirmacion = false;

if (es_post()) {
    csrf_verificar();
    $pdo = db();
    $pdo->beginTransaction();
    if ($material) {
        q('SELECT id FROM materiales WHERE id = ? FOR UPDATE', [$material['id']]); // evita prestar dos veces el último ejemplar
        $material = material_por_id($material['id']);
    }
    $resultado = validar_prestamo($lector, $material, $vence);
    // La confirmación solo vale para la misma combinación lector-material que generó la advertencia
    $confirmado = $lector && $material && p('confirmar') === $material['id'] . '-' . $lector['id'];

    if (!$resultado['errores'] && (!$resultado['advertencias'] || $confirmado)) {
        $unico = (int)$material['disponibles'] === 1 ? 1 : 0;
        q('INSERT INTO prestamos (material_id, lector_id, fecha_prestamo, fecha_vencimiento, registrado_por, aviso_unico_ejemplar, observacion)
           VALUES (?, ?, NOW(), ?, ?, ?, ?)',
          [$material['id'], $lector['id'], $vence, usuario()['id'], $unico, $obs !== '' ? mb_substr($obs, 0, 255) : null]);
        $pdo->commit();
        flash('ok', 'Préstamo registrado: «' . $material['titulo'] . '» para ' . nombre_completo($lector) . '. Devolver hasta el ' . fecha_es($vence) . '.');
        redirigir('prestamos');
    }
    $pdo->rollBack();
    $pedirConfirmacion = !$resultado['errores'] && $resultado['advertencias'];
}

$sitLector = $lector && $lector['rol'] === 'lector' ? situacion_lector((int)$lector['id']) : null;

pagina_inicio('Nuevo préstamo', 'prestamos');
encabezado('Registrar préstamo', 'Busque al lector y el material; el sistema verifica las reglas antes de guardar.',
    '<a class="btn" href="' . e(url('prestamos')) . '">‹ Préstamos</a>');

foreach ($resultado['errores'] as $er) echo '<div class="aviso aviso-error">' . e($er) . '</div>';
if ($pedirConfirmacion) foreach ($resultado['advertencias'] as $a) echo '<div class="aviso aviso-advertencia"><strong>Advertencia:</strong> ' . e($a) . '</div>';
?>
<form method="post" class="tarjeta formulario wizard-card" action="<?= e(url('prestamo_nuevo')) ?>" id="form-prestamo" data-wizard-step="<?= $pedirConfirmacion ? '2' : '0' ?>">
  <?= csrf_campo() ?>
  <div class="grupo-form-titulo"><?= icono('plus') ?> Registro de nuevo préstamo <span class="estado est-ambar" style="margin-left:auto">Biblioteca I.E. San Juan</span></div>
  <div class="pasos" id="pasos-prestamo" aria-label="Pasos de registro"><span class="paso activo">1. Lector</span><span class="paso">2. Material</span><span class="paso">3. Confirmación</span></div>
  <div class="rejilla-2 sin-margen wizard-selectores">
    <div class="selector" data-tipo="lector">
      <label>Lector (DNI, nombre o correo)
        <input type="search" class="selector-buscar" autocomplete="off" placeholder="Escriba al menos 2 caracteres…"
               value="<?= $lector ? e(nombre_completo($lector) . ' · DNI ' . $lector['dni']) : '' ?>">
      </label>
      <input type="hidden" name="lector_id" class="selector-id" value="<?= $lector ? (int)$lector['id'] : '' ?>">
      <ul class="selector-resultados" hidden></ul>
      <div class="selector-info">
        <?php if ($lector && $sitLector): ?>
          <?= e(ucfirst((string)$lector['tipo_lector'])) ?><?= $lector['grado'] ? ' · ' . e($lector['grado'] . ' ' . $lector['seccion']) : '' ?> ·
          Activos: <?= $sitLector['activos'] ?><?= limite_lector($lector) ? ' de ' . limite_lector($lector) : '' ?>
          <?= $sitLector['vencidos'] ? ' · <span class="estado est-rojo">' . $sitLector['vencidos'] . ' vencido(s)</span>' : '' ?>
        <?php endif; ?>
      </div>
    </div>
    <div class="selector" data-tipo="material">
      <label>Material (código o título)
        <input type="search" class="selector-buscar" autocomplete="off" placeholder="Escriba al menos 2 caracteres…"
               value="<?= $material ? e($material['codigo'] . ' · ' . $material['titulo']) : '' ?>">
      </label>
      <input type="hidden" name="material_id" class="selector-id" value="<?= $material ? (int)$material['id'] : '' ?>">
      <ul class="selector-resultados" hidden></ul>
      <div class="selector-info">
        <?php if ($material): ?><?= disponibilidad_html($material) ?> · <?= e($material['tipo_nombre'] ?: 'Tipo no especificado') ?> · Ubicación: <?= e($material['ubicacion'] ?: '—') ?><?php endif; ?>
      </div>
    </div>
  </div>
  <div class="rejilla-form wizard-fechas">
    <label>Fecha de devolución *
      <input type="date" name="fecha_vencimiento" id="f-vence" value="<?= e($vence) ?>" min="<?= hoy() ?>" required>
      <small class="ayuda">Se propone según el tipo de material. Puede ajustarla.</small>
    </label>
    <label>Observación<input name="observacion" value="<?= e($obs) ?>" maxlength="255"></label>
  </div>
  <section class="ayuda-panel wizard-resumen" <?= $pedirConfirmacion ? '' : 'hidden' ?> aria-live="polite"><h2>Confirma el préstamo</h2><dl class="datos-grandes"><dt>Lector</dt><dd id="resumen-lector"><?= $lector ? e(nombre_completo($lector)) : '' ?></dd><dt>Material</dt><dd id="resumen-material"><?= $material ? e($material['codigo'] . ' · ' . $material['titulo']) : '' ?></dd><dt>Devolver hasta</dt><dd id="resumen-fecha"><?= e($vence) ?></dd></dl><p class="nota">Revisa los datos. El sistema validará las reglas y la disponibilidad antes de guardar.</p></section>
  <div class="botonera">
    <a class="btn" href="<?= e(url('prestamos')) ?>">Cancelar</a>
    <button type="button" class="btn wizard-atras" hidden>Anterior</button>
    <button type="button" class="btn btn-primario wizard-siguiente" hidden>Continuar al paso 2 <?= icono('arrow') ?></button>
    <?php if ($pedirConfirmacion): ?>
      <input type="hidden" name="confirmar" value="<?= (int)$material['id'] . '-' . (int)$lector['id'] ?>">
      <button class="btn btn-advertencia">Confirmar préstamo</button>
    <?php else: ?>
      <button class="btn btn-primario">Registrar préstamo</button>
    <?php endif; ?>
  </div>
  <p class="nota">Se guardará automáticamente su nombre como responsable, con la fecha y hora del registro.</p>
</form>
<?php pagina_fin();
