<?php
// RF-18: préstamos activos y fechas previstas de devolución (vista del personal). Punto de acceso a devoluciones (RF-16) y renovaciones (RF-15).
$texto = g('q');
$filtro = g('filtro', 'activos');
$filtros = ['activos' => 'Todos los activos', 'vencidos' => 'Vencidos', 'proximos' => 'Próximos a vencer', 'perdidos' => 'Perdidos (reposición pendiente)'];
if (!isset($filtros[$filtro])) $filtro = 'activos';

$where = [];
$params = [];
switch ($filtro) {
    case 'vencidos': $where[] = "p.estado = 'activo' AND p.fecha_vencimiento < CURDATE()"; break;
    case 'proximos': $where[] = "p.estado = 'activo' AND p.fecha_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)"; $params[] = (int)conf('dias_alerta_proximo', 2); break;
    case 'perdidos': $where[] = "p.estado = 'perdido'"; break;
    default:         $where[] = "p.estado = 'activo'";
}
if ($texto !== '') {
    $like = "%$texto%";
    $where[] = "(u.dni LIKE ? OR CONCAT(u.nombres,' ',u.apellidos) LIKE ? OR m.codigo LIKE ? OR m.titulo LIKE ?)";
    array_push($params, $like, $like, $like, $like);
}
$sql = "FROM prestamos p JOIN materiales m ON m.id = p.material_id JOIN usuarios u ON u.id = p.lector_id
        JOIN usuarios r ON r.id = p.registrado_por WHERE " . implode(' AND ', $where);
$total = (int)valor("SELECT COUNT(*) $sql", $params);
[$offset, $limite, $pag] = paginacion(30);
$lista = filas("SELECT p.*, m.codigo, m.titulo, u.nombres, u.apellidos, u.dni, u.grado, u.seccion, u.tipo_lector,
                       r.nombres AS r_nombres, r.apellidos AS r_apellidos
                $sql ORDER BY p.fecha_vencimiento, u.apellidos LIMIT $limite OFFSET $offset", $params);

pagina_inicio('Préstamos', 'prestamos');
encabezado('Control de Préstamos', 'Gestión de circulación · ' . $total . ' registro(s)', '<a class="btn btn-primario" href="' . e(url('prestamo_nuevo')) . '">+ Nuevo préstamo</a>');
?>
<nav class="tabs" aria-label="Filtrar préstamos"><?php foreach ($filtros as $k => $v): ?><a class="<?= $filtro === $k ? 'activo' : '' ?>" href="<?= e(url('prestamos', array_filter(['filtro'=>$k,'q'=>$texto]))) ?>" <?= $filtro === $k ? 'aria-current="page"' : '' ?>><?= e($v) ?></a><?php endforeach; ?></nav>
<form method="get" class="buscador tarjeta" action="index.php">
  <input type="hidden" name="p" value="prestamos">
  <div class="fila-busqueda">
    <input type="search" name="q" value="<?= e($texto) ?>" placeholder="Lector (DNI o nombre), código o título…">
    <select name="filtro"><?php foreach ($filtros as $k => $v): ?><option value="<?= $k ?>" <?= $k === $filtro ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select>
    <button class="btn btn-primario">Filtrar</button>
  </div>
</form>
<?php if (!$lista): ?>
  <div class="vacio">No hay préstamos con ese criterio.</div>
<?php else: ?>
<div class="tabla-envoltura"><table class="tabla">
  <thead><tr><th>Lector</th><th>Material</th><th>Prestado</th><th>Devolver hasta</th><th>Estado</th><th>Registrado por</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($lista as $r): ?>
    <tr>
      <td><strong><?= e(nombre_completo($r)) ?></strong><br><small>DNI <?= e($r['dni']) ?><?= $r['grado'] ? ' · ' . e($r['grado'] . ' ' . $r['seccion']) : ' · ' . e(ucfirst((string)$r['tipo_lector'])) ?></small></td>
      <td><span class="mono"><?= e($r['codigo']) ?></span><br><small><?= e($r['titulo']) ?></small></td>
      <td><?= fecha_es($r['fecha_prestamo'], true) ?></td>
      <td><?= fecha_es($r['fecha_vencimiento']) ?><?= $r['renovaciones'] ? '<br><small>Renovado ' . (int)$r['renovaciones'] . ' vez/veces</small>' : '' ?></td>
      <td><?= estado_prestamo_html($r) ?></td>
      <td><small><?= e($r['r_nombres'] . ' ' . $r['r_apellidos']) ?></small></td>
      <td class="acciones-fila">
        <?php if ($r['estado'] === 'activo'): ?>
          <a class="btn btn-chico btn-primario" href="<?= e(url('devolucion', ['id' => $r['id']])) ?>">Devolver</a>
          <?php if (puede('prestamos.renovar')): ?><a class="btn btn-chico" href="<?= e(url('renovacion', ['id' => $r['id']])) ?>">Renovar</a><?php endif; ?>
        <?php else: ?>
          <a class="btn btn-chico btn-primario" href="<?= e(url('reposicion', ['id' => $r['id']])) ?>">Registrar reposición</a>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php endif; ?>
<?= enlaces_paginacion($total, $limite, $pag, 'prestamos', array_filter(['q' => $texto, 'filtro' => $filtro])) ?>
<?php pagina_fin();
