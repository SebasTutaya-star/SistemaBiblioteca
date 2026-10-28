<?php
// RF-18: historial general de préstamos y devoluciones (Bibliotecaria y Auxiliar). RF-17: responsables con fecha y hora.
$texto = g('q');
$estado = g('estado');
$desde = g('desde');
$hasta = g('hasta');
$estados = ['' => 'Todos', 'activo' => 'Activos', 'devuelto' => 'Devueltos', 'perdido' => 'Perdidos (pendientes)', 'repuesto' => 'Perdidos repuestos'];
if (!isset($estados[$estado])) $estado = '';

$where = ['1=1'];
$params = [];
if ($estado !== '') { $where[] = 'p.estado = ?'; $params[] = $estado; }
if (fecha_valida($desde)) { $where[] = 'p.fecha_prestamo >= ?'; $params[] = "$desde 00:00:00"; }
if (fecha_valida($hasta)) { $where[] = 'p.fecha_prestamo <= ?'; $params[] = "$hasta 23:59:59"; }
if ($texto !== '') {
    $like = "%$texto%";
    $where[] = "(u.dni LIKE ? OR CONCAT(u.nombres,' ',u.apellidos) LIKE ? OR m.codigo LIKE ? OR m.titulo LIKE ?)";
    array_push($params, $like, $like, $like, $like);
}
$sql = "FROM prestamos p JOIN materiales m ON m.id = p.material_id JOIN usuarios u ON u.id = p.lector_id
        JOIN usuarios r ON r.id = p.registrado_por LEFT JOIN usuarios d ON d.id = p.recibido_por
        WHERE " . implode(' AND ', $where);
$total = (int)valor("SELECT COUNT(*) $sql", $params);
[$offset, $limite, $pag] = paginacion(40);
$lista = filas("SELECT p.*, m.codigo, m.titulo, u.nombres, u.apellidos, u.dni,
                CONCAT(r.nombres,' ',r.apellidos) AS registro, CONCAT(d.nombres,' ',d.apellidos) AS recepcion
                $sql ORDER BY p.fecha_prestamo DESC LIMIT $limite OFFSET $offset", $params);

pagina_inicio('Historial', 'historial');
encabezado('Historial de préstamos y devoluciones', $total . ' registro(s)');
?>
<form method="get" class="buscador tarjeta" action="index.php">
  <input type="hidden" name="p" value="historial">
  <div class="fila-busqueda">
    <input type="search" name="q" value="<?= e($texto) ?>" placeholder="Lector (DNI o nombre), código o título…">
    <select name="estado"><?php foreach ($estados as $k => $v): ?><option value="<?= $k ?>" <?= $k === $estado ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select>
  </div>
  <div class="filtros">
    <label class="en-linea">Prestado desde <input type="date" name="desde" value="<?= e($desde) ?>"></label>
    <label class="en-linea">hasta <input type="date" name="hasta" value="<?= e($hasta) ?>"></label>
    <button class="btn btn-primario">Filtrar</button>
  </div>
</form>
<?php if (!$lista): ?><div class="vacio">Sin registros.</div><?php else: ?>
<div class="tabla-envoltura"><table class="tabla">
  <thead><tr><th>Lector</th><th>Material</th><th>Préstamo</th><th>Vencimiento</th><th>Devolución</th><th>Estado</th></tr></thead>
  <tbody><?php foreach ($lista as $r): ?>
    <tr>
      <td><?= e(nombre_completo($r)) ?><br><small>DNI <?= e($r['dni']) ?></small></td>
      <td><span class="mono"><?= e($r['codigo']) ?></span><br><small><?= e($r['titulo']) ?></small></td>
      <td><?= fecha_es($r['fecha_prestamo'], true) ?><br><small>por <?= e($r['registro']) ?></small></td>
      <td><?= fecha_es($r['fecha_vencimiento']) ?><?= $r['renovaciones'] ? '<br><small>' . (int)$r['renovaciones'] . ' renovación(es)</small>' : '' ?></td>
      <td><?php if ($r['fecha_devolucion']): ?><?= fecha_es($r['fecha_devolucion'], true) ?><br><small>por <?= e($r['recepcion']) ?><?= $r['condicion_devolucion'] === 'deteriorado' ? ' · deteriorado' : '' ?></small><?php else: ?>—<?php endif; ?></td>
      <td><?= estado_prestamo_html($r) ?></td>
    </tr>
  <?php endforeach; ?></tbody>
</table></div>
<?php endif; ?>
<?= enlaces_paginacion($total, $limite, $pag, 'historial', array_filter(['q' => $texto, 'estado' => $estado, 'desde' => $desde, 'hasta' => $hasta])) ?>
<?php pagina_fin();
