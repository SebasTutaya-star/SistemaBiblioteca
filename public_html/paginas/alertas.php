<?php
// RF-19: alertas de préstamos próximos a vencer, vencidos y lectores con materiales pendientes.
$dias = (int)conf('dias_alerta_proximo', 2);
$base = "SELECT p.*, m.codigo, m.titulo, m.ubicacion, u.nombres, u.apellidos, u.dni, u.tipo_lector, u.grado, u.seccion, u.correo
         FROM prestamos p JOIN materiales m ON m.id = p.material_id JOIN usuarios u ON u.id = p.lector_id";
$vencidos = filas("$base WHERE p.estado = 'activo' AND p.fecha_vencimiento < CURDATE() ORDER BY p.fecha_vencimiento, u.grado, u.seccion");
$proximos = filas("$base WHERE p.estado = 'activo' AND p.fecha_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY) ORDER BY p.fecha_vencimiento", [$dias]);
$pendientes = filas("SELECT u.id, u.nombres, u.apellidos, u.dni, u.tipo_lector, u.grado, u.seccion,
                            SUM(p.estado = 'activo') AS activos,
                            SUM(p.estado = 'activo' AND p.fecha_vencimiento < CURDATE()) AS vencidos,
                            SUM(p.estado = 'perdido') AS perdidos,
                            GROUP_CONCAT(m.titulo ORDER BY p.fecha_vencimiento SEPARATOR ' · ') AS materiales
                     FROM prestamos p JOIN usuarios u ON u.id = p.lector_id JOIN materiales m ON m.id = p.material_id
                     WHERE p.estado IN ('activo','perdido')
                     GROUP BY u.id ORDER BY vencidos DESC, perdidos DESC, u.grado, u.seccion, u.apellidos");

function tabla_alertas(array $filas): void
{
    if (!$filas) { echo '<p class="vacio">Nada pendiente.</p>'; return; }
    echo '<div class="tabla-envoltura"><table class="tabla"><thead><tr><th>Lector</th><th>Grado / sección</th><th>Material</th><th>Devolver hasta</th><th>Estado</th><th></th></tr></thead><tbody>';
    foreach ($filas as $r) {
        echo '<tr><td><strong>' . e(nombre_completo($r)) . '</strong><br><small>DNI ' . e($r['dni']) . '</small></td>'
           . '<td>' . ($r['grado'] ? e($r['grado'] . ' ' . $r['seccion']) : e(ucfirst((string)$r['tipo_lector']))) . '</td>'
           . '<td><span class="mono">' . e($r['codigo']) . '</span><br><small>' . e($r['titulo']) . '</small></td>'
           . '<td>' . fecha_es($r['fecha_vencimiento']) . '</td><td>' . estado_prestamo_html($r) . '</td>'
           . '<td class="acciones-fila"><a class="btn btn-chico" href="' . e(url('devolucion', ['id' => $r['id']])) . '">Devolver</a></td></tr>';
    }
    echo '</tbody></table></div>';
}

pagina_inicio('Alertas', 'alertas');
encabezado('Alertas y notificaciones', 'Actualizado al ' . fecha_es(date('Y-m-d H:i'), true));
?>
<div class="metricas"><?php ui_metrica('Préstamos vencidos', count($vencidos), 'Requieren regularización', 'bell', 'roja'); ui_metrica('Próximos vencimientos', count($proximos), 'Dentro del plazo de aviso', 'clock', 'naranja'); ui_metrica('Lectores con pendientes', count($pendientes), 'Materiales por devolver', 'users'); ui_metrica('Anticipación de alertas', $dias . ' días', 'Configuración vigente', 'calendar'); ?></div>
<nav class="tabs" aria-label="Tipos de alerta"><a href="#alertas-vencidos">Vencidos</a><a href="#alertas-proximos">Próximos a vencer</a><a href="#alertas-pendientes">Lectores con pendientes</a></nav>
<section class="tarjeta borde-rojo" id="alertas-vencidos">
  <h2>Préstamos vencidos <span class="contador"><?= count($vencidos) ?></span></h2>
  <?php tabla_alertas($vencidos); ?>
</section>
<section class="tarjeta borde-ambar" id="alertas-proximos">
  <h2>Próximos a vencer (hoy y <?= $dias ?> día(s) siguientes) <span class="contador"><?= count($proximos) ?></span></h2>
  <?php tabla_alertas($proximos); ?>
</section>
<section class="tarjeta" id="alertas-pendientes">
  <h2>Lectores con materiales pendientes de devolución <span class="contador"><?= count($pendientes) ?></span></h2>
  <?php if (!$pendientes): ?><p class="vacio">Ningún lector tiene materiales pendientes.</p><?php else: ?>
  <div class="tabla-envoltura"><table class="tabla">
    <thead><tr><th>Lector</th><th>Grado / sección</th><th class="num">En préstamo</th><th class="num">Vencidos</th><th class="num">Perdidos</th><th>Materiales</th></tr></thead>
    <tbody><?php foreach ($pendientes as $r): ?>
      <tr><td><strong><?= e(nombre_completo($r)) ?></strong><br><small>DNI <?= e($r['dni']) ?></small></td>
          <td><?= $r['grado'] ? e($r['grado'] . ' ' . $r['seccion']) : e(ucfirst((string)$r['tipo_lector'])) ?></td>
          <td class="num"><?= (int)$r['activos'] ?></td>
          <td class="num"><?= $r['vencidos'] ? '<span class="estado est-rojo">' . (int)$r['vencidos'] . '</span>' : '0' ?></td>
          <td class="num"><?= $r['perdidos'] ? '<span class="estado est-rojo">' . (int)$r['perdidos'] . '</span>' : '0' ?></td>
          <td><small><?= e($r['materiales']) ?></small></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
</section>
<?php pagina_fin();
