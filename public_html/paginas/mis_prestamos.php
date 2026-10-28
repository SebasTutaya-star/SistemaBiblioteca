<?php
// RF-18: el Lector consulta únicamente sus propios préstamos, fechas de devolución e historial.
$u = usuario();
$activos = filas("SELECT p.*, m.codigo, m.titulo, m.autor FROM prestamos p JOIN materiales m ON m.id = p.material_id
                  WHERE p.lector_id = ? AND p.estado IN ('activo','perdido') ORDER BY p.fecha_vencimiento", [$u['id']]);
[$offset, $limite, $pag] = paginacion(20);
$total = (int)valor("SELECT COUNT(*) FROM prestamos WHERE lector_id = ? AND estado IN ('devuelto','repuesto')", [$u['id']]);
$historial = filas("SELECT p.*, m.codigo, m.titulo, m.autor FROM prestamos p JOIN materiales m ON m.id = p.material_id
                    WHERE p.lector_id = ? AND p.estado IN ('devuelto','repuesto') ORDER BY p.fecha_prestamo DESC LIMIT $limite OFFSET $offset", [$u['id']]);

pagina_inicio('Mis préstamos', 'mis_prestamos');
encabezado('Mis préstamos', 'Libros que tiene en su poder y su historial.');
?>
<section class="tarjeta">
  <h2>En mi poder</h2>
  <?php if (!$activos): ?><p class="vacio">No tiene libros prestados.</p><?php else: ?>
  <div class="tabla-envoltura"><table class="tabla">
    <thead><tr><th>Libro</th><th>Prestado el</th><th>Devolver hasta</th><th>Estado</th></tr></thead>
    <tbody><?php foreach ($activos as $r): ?>
      <tr><td><strong><?= e($r['titulo']) ?></strong><br><small><?= e($r['autor']) ?> · <?= e($r['codigo']) ?></small></td>
          <td><?= fecha_es($r['fecha_prestamo']) ?></td>
          <td><strong><?= fecha_es($r['fecha_vencimiento']) ?></strong><?= $r['renovaciones'] ? '<br><small>Plazo ampliado</small>' : '' ?></td>
          <td><?= estado_prestamo_html($r) ?></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <p class="nota">Si necesita más tiempo, solicite la ampliación en la biblioteca indicando el motivo.</p>
  <?php endif; ?>
</section>
<section class="tarjeta">
  <h2>Historial</h2>
  <?php if (!$historial): ?><p class="vacio">Aún no tiene préstamos devueltos.</p><?php else: ?>
  <div class="tabla-envoltura"><table class="tabla">
    <thead><tr><th>Libro</th><th>Prestado el</th><th>Devuelto el</th></tr></thead>
    <tbody><?php foreach ($historial as $r): ?>
      <tr><td><?= e($r['titulo']) ?><br><small><?= e($r['autor']) ?></small></td>
          <td><?= fecha_es($r['fecha_prestamo']) ?></td>
          <td><?= fecha_es($r['estado'] === 'repuesto' ? $r['fecha_reposicion'] : $r['fecha_devolucion']) ?><?= $r['estado'] === 'repuesto' ? ' (repuesto)' : '' ?></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?= enlaces_paginacion($total, $limite, $pag, 'mis_prestamos', []) ?>
  <?php endif; ?>
</section>
<?php pagina_fin();
