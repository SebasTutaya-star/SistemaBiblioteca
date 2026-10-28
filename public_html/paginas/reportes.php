<?php
// RF-20: reportes con filtro por rango de fechas y exportación a Excel y PDF (solo Bibliotecaria).
require_once RAIZ . '/app/reportes.php';

$tipo = g('tipo', 'prestamos');
if (!isset(TIPOS_REPORTE[$tipo])) $tipo = 'prestamos';
$desde = g('desde');
$hasta = g('hasta');
if (!fecha_valida($desde)) $desde = date('Y-m-01');
if (!fecha_valida($hasta)) $hasta = date('Y-m-d');
if ($desde > $hasta) [$desde, $hasta] = [$hasta, $desde];
$mes = (int)g('mes', date('n'));
$anio = (int)g('anio', date('Y'));
if ($mes < 1 || $mes > 12) $mes = (int)date('n');
if ($anio < 2020 || $anio > 2100) $anio = (int)date('Y');

$rep = generar_reporte($tipo, $desde, $hasta, $mes, $anio);
$formato = g('formato');
$archivo = 'reporte_' . $tipo . '_' . date('Ymd_His');

if ($formato === 'xlsx') {
    require_once RAIZ . '/app/lib/excel.php';
    if (class_exists('ZipArchive')) {
        descargar(xlsx_generar($rep['titulo'] . ' — ' . $rep['periodo'], $rep['columnas'], $rep['filas'], TIPOS_REPORTE[$tipo]),
                  "$archivo.xlsx", 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }
    // Alternativa si el servidor no tiene ZIP: CSV que Excel abre directamente
    $h = fopen('php://temp', 'r+');
    fwrite($h, "\xEF\xBB\xBF");
    fputcsv($h, [$rep['titulo'] . ' — ' . $rep['periodo']], ';', '"', '');
    fputcsv($h, $rep['columnas'], ';', '"', '');
    foreach ($rep['filas'] as $f) fputcsv($h, $f, ';', '"', '');
    rewind($h);
    descargar(stream_get_contents($h), "$archivo.csv", 'text/csv; charset=utf-8');
}
if ($formato === 'pdf') {
    require_once RAIZ . '/app/lib/pdf.php';
    $pdf = new PdfTabla($rep['titulo'], $rep['periodo'] . ' · Generado por ' . nombre_completo(usuario()), count($rep['columnas']) > 3);
    $pdf->tabla($rep['columnas'], $rep['filas']);
    descargar($pdf->salida(), "$archivo.pdf", 'application/pdf');
}

$usaRango = in_array($tipo, ['prestamos', 'devoluciones'], true);

pagina_inicio('Reportes', 'reportes');
encabezado('Reportes de la biblioteca', 'Elija el reporte y el periodo; luego descárguelo en Excel o PDF.');
?>
<form method="get" class="tarjeta formulario" action="index.php" id="form-reporte">
  <input type="hidden" name="p" value="reportes">
  <div class="grupo-form-titulo"><?= icono('chart') ?> Generar reporte de biblioteca</div>
  <div class="rejilla-form">
    <label>Reporte
      <select name="tipo" onchange="this.form.submit()">
        <?php foreach (TIPOS_REPORTE as $k => $v): ?><option value="<?= $k ?>" <?= $k === $tipo ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
      </select>
    </label>
    <?php if ($usaRango): ?>
      <label>Desde<input type="date" name="desde" value="<?= e($desde) ?>"></label>
      <label>Hasta<input type="date" name="hasta" value="<?= e($hasta) ?>"></label>
    <?php elseif ($tipo === 'mensual'): ?>
      <label>Mes<select name="mes"><?php foreach (MESES as $n => $nom): ?><option value="<?= $n ?>" <?= $n === $mes ? 'selected' : '' ?>><?= $nom ?></option><?php endforeach; ?></select></label>
      <label>Año<input type="number" name="anio" value="<?= $anio ?>" min="2020" max="2100"></label>
    <?php elseif ($tipo === 'anual'): ?>
      <label>Año<input type="number" name="anio" value="<?= $anio ?>" min="2020" max="2100"></label>
    <?php else: ?>
      <p class="nota col-2">Este reporte muestra la situación actual; no requiere fechas.</p>
    <?php endif; ?>
  </div>
  <div class="botonera izquierda">
    <button class="btn btn-primario">Ver reporte</button>
    <button class="btn" name="formato" value="xlsx"><?= icono('download') ?> Descargar Excel</button>
    <button class="btn" name="formato" value="pdf"><?= icono('download') ?> Descargar PDF</button>
  </div>
</form>

<section class="tarjeta">
  <h2><?= e($rep['titulo']) ?></h2>
  <p class="sub"><?= e($rep['periodo']) ?> · <?= count($rep['filas']) ?> fila(s)</p>
  <?php if (!$rep['filas']): ?><p class="vacio">Sin registros para el periodo seleccionado.</p><?php else: ?>
  <div class="tabla-envoltura"><table class="tabla">
    <thead><tr><?php foreach ($rep['columnas'] as $c): ?><th><?= e($c) ?></th><?php endforeach; ?></tr></thead>
    <tbody><?php foreach ($rep['filas'] as $f): ?><tr><?php foreach ($f as $v): ?><td<?= is_int($v) ? ' class="num"' : '' ?>><?= e($v) ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
  <p class="nota">Los reportes presentan datos de gestión. Las conclusiones y recomendaciones institucionales las redacta la Bibliotecaria.</p>
</section>
<?php pagina_fin();
