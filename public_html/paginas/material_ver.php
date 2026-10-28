<?php
// Ficha del material. El Lector ve datos bibliográficos (RF-09); el personal ve además ubicación, cantidades e historial (RF-10).
$gestion = puede('catalogo.gestionar');
$m = material_por_id((int)g('id'));
if (!$m || (!$m['activo'] && !$gestion)) {
    flash('error', 'El material no existe o no está disponible.');
    redirigir('catalogo');
}
if (!$gestion) {
    // Restricción en la capa de datos: el Lector no recibe la ubicación física ni datos internos.
    unset($m['ubicacion'], $m['cantidad_deteriorada'], $m['cantidad_perdida'], $m['creado_por']);
}

pagina_inicio($m['titulo'], 'catalogo');
$acciones = '<a class="btn" href="' . e(url('catalogo')) . '">‹ Catálogo</a>';
if ($gestion) {
    $acciones .= ' <a class="btn" href="' . e(url('material_form', ['id' => $m['id']])) . '">Editar</a>'
              .  ' <a class="btn btn-primario" href="' . e(url('material_estado', ['id' => $m['id']])) . '">Estados y cantidades</a>';
}
encabezado($m['titulo'], $m['autor'] ?: '', $acciones);
?>
<div class="rejilla-2">
  <section class="tarjeta">
    <h2>Datos bibliográficos</h2>
    <dl class="datos-grandes">
      <dt>Código</dt><dd class="mono"><?= e($m['codigo']) ?></dd>
      <dt>Autor</dt><dd><?= e($m['autor'] ?: '—') ?></dd>
      <dt>Editorial</dt><dd><?= e($m['editorial'] ?: '—') ?></dd>
      <dt>Año</dt><dd><?= e($m['anio'] ?: '—') ?></dd>
      <dt>Área</dt><dd><?= e($m['area'] ?: '—') ?></dd>
      <dt>Nivel / grado</dt><dd><?= e($m['nivel'] ?: '—') ?></dd>
      <dt>Tema</dt><dd><?= e($m['tema'] ?: '—') ?></dd>
      <dt>Tipo de material</dt><dd><?= e($m['tipo_nombre'] ?: '—') ?></dd>
      <dt>Disponibilidad</dt><dd><?= disponibilidad_html($m) ?></dd>
    </dl>
    <?php if ($m['descripcion']): ?><h3>Descripción</h3><p><?= nl2br(e($m['descripcion'])) ?></p><?php endif; ?>
  </section>

  <?php if ($gestion): ?>
  <section class="tarjeta">
    <h2>Información administrativa</h2>
    <dl class="datos-grandes">
      <dt>Ubicación física</dt><dd><strong><?= e($m['ubicacion'] ?: 'Sin registrar') ?></strong></dd>
      <dt>Estado del registro</dt><dd><?= $m['activo'] ? 'Activo' : '<span class="estado est-gris">Inactivo</span>' ?></dd>
    </dl>
    <div class="cantidades">
      <div><span><?= (int)$m['cantidad_total'] ?></span>Total</div>
      <div class="c-verde"><span><?= (int)$m['disponibles'] ?></span>Disponibles</div>
      <div class="c-azul"><span><?= (int)$m['prestados'] ?></span>Prestados</div>
      <div class="c-ambar"><span><?= (int)$m['cantidad_deteriorada'] ?></span>Deteriorados</div>
      <div class="c-rojo"><span><?= (int)$m['cantidad_perdida'] ?></span>Perdidos</div>
    </div>
    <?php
    $activos = filas("SELECT p.*, u.nombres, u.apellidos, u.grado, u.seccion FROM prestamos p JOIN usuarios u ON u.id = p.lector_id
                      WHERE p.material_id = ? AND p.estado IN ('activo','perdido') ORDER BY p.fecha_vencimiento", [$m['id']]);
    if ($activos): ?>
      <h3>Ejemplares fuera de la biblioteca</h3>
      <ul class="lista-simple">
      <?php foreach ($activos as $a): ?>
        <li><?= e(nombre_completo($a)) ?> <?= $a['grado'] ? '(' . e($a['grado'] . ' ' . $a['seccion']) . ')' : '' ?> — vence <?= fecha_es($a['fecha_vencimiento']) ?> <?= estado_prestamo_html($a) ?></li>
      <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
  <?php endif; ?>
</div>

<?php if ($gestion):
  $movs = filas('SELECT mv.*, u.nombres, u.apellidos FROM movimientos_inventario mv JOIN usuarios u ON u.id = mv.usuario_id
                 WHERE mv.material_id = ? ORDER BY mv.fecha DESC LIMIT 30', [$m['id']]);
  $etq = ['alta'=>'Registro','importacion'=>'Importación','ajuste_cantidad'=>'Ajuste de cantidad','deterioro'=>'Deteriorado',
          'reparado'=>'Recuperado/reparado','perdida'=>'Pérdida','reposicion'=>'Reposición','inactivar'=>'Inactivado','activar'=>'Reactivado','edicion'=>'Edición'];
?>
<section class="tarjeta">
  <h2>Historial del material</h2>
  <?php if (!$movs): ?><p class="vacio">Sin movimientos registrados.</p><?php else: ?>
  <div class="tabla-envoltura"><table class="tabla">
    <thead><tr><th>Fecha y hora</th><th>Movimiento</th><th class="num">Cantidad</th><th>Observación</th><th>Responsable</th></tr></thead>
    <tbody><?php foreach ($movs as $mv): ?>
      <tr><td><?= fecha_es($mv['fecha'], true) ?></td><td><?= e($etq[$mv['tipo']] ?? $mv['tipo']) ?></td>
          <td class="num"><?= (int)$mv['cantidad'] ?></td><td><?= e($mv['observacion']) ?></td><td><?= e(nombre_completo($mv)) ?></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
</section>
<?php endif; ?>
<?php pagina_fin();
