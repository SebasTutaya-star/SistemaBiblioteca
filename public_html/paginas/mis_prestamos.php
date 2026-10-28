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
encabezado('Mis Préstamos en Curso', 'Revisa tus lecturas, fechas de entrega e historial de devoluciones.');
?>
<div class="cupo" style="margin:0 0 20px"><div class="cupo-top"><span><?= icono('book') ?> Capacidad de préstamo <?= e(ucfirst($u['tipo_lector'])) ?></span><span><?= count(array_filter($activos, fn($r) => $r['estado'] === 'activo')) ?><?= limite_lector($u) ? ' de ' . limite_lector($u) : '' ?> libro(s)</span></div><?php if (limite_lector($u)): ?><progress value="<?= min(limite_lector($u), count(array_filter($activos, fn($r) => $r['estado'] === 'activo'))) ?>" max="<?= limite_lector($u) ?>" aria-label="Cupo de préstamos utilizado"></progress><?php endif; ?></div>
<div class="tabs" data-tabs role="tablist" aria-label="Mis lecturas"><button type="button" id="tab-activos" data-panel="activos" role="tab" aria-controls="activos" aria-selected="true"><?= icono('book') ?> Activos (<?= count($activos) ?>)</button><button type="button" id="tab-historial" data-panel="historial" role="tab" aria-controls="historial" aria-selected="false"><?= icono('clock') ?> Devueltos (<?= $total ?>)</button></div>
<section id="activos" role="tabpanel" aria-labelledby="tab-activos">
<?php if (!$activos): ?><div class="vacio">No tienes libros prestados. <a href="<?= e(url('catalogo')) ?>">Busca tu próxima lectura en el catálogo.</a></div><?php endif; ?>
<?php foreach ($activos as $r): ?>
<article class="tarjeta"><div class="ficha-cab"><span class="sobrelinea"><?= icono('calendar') ?> Plazo de lectura · <?= e($r['codigo']) ?></span><?= estado_prestamo_html($r) ?></div><div class="libro-activo"><?= ui_portada($r) ?><div class="libro-activo-info"><h3><a href="<?= e(url('material_ver', ['id'=>$r['material_id']])) ?>"><?= e($r['titulo']) ?></a></h3><p class="sub"><?= e($r['autor']) ?></p><div class="fechas-prestamo"><div>Fecha de retiro<strong><?= fecha_es($r['fecha_prestamo']) ?></strong></div><div>Devolución hasta<strong><?= fecha_es($r['fecha_vencimiento']) ?></strong><?= $r['renovaciones'] ? '<small>Plazo ampliado</small>' : '' ?></div></div><details class="ayuda-panel"><summary>¿Cómo solicitar una ampliación de lectura?</summary><p style="margin-top:12px">Acércate al personal de biblioteca antes del vencimiento e indica el motivo. El personal revisará tu solicitud y registrará la autorización.</p></details></div></div></article>
<?php endforeach; ?>
<div class="ayuda-panel"><h3>¿Planeando tu siguiente lectura?</h3><p>Explora obras de historia, ciencia y literatura en el catálogo escolar.</p><a class="btn btn-primario" href="<?= e(url('catalogo')) ?>">Explorar Catálogo San Juan <?= icono('arrow') ?></a></div>
</section>
<section id="historial" role="tabpanel" aria-labelledby="tab-historial" hidden>
<div class="perfil-carnet"><small>Carnet bibliotecario escolar</small><h2><?= e(nombre_completo($u)) ?></h2><p><?= e($u['grado'] ? $u['grado'] . ' · Sección ' . $u['seccion'] : ucfirst($u['tipo_lector'])) ?></p><div class="perfil-datos"><div><small>DNI del lector</small><strong><?= e($u['dni']) ?></strong></div><div><small>Biblioteca</small><strong>I.E. San Juan</strong></div></div></div>
<div class="seccion-titulo"><h2>Historial de lecturas</h2><a class="btn btn-chico" href="<?= e(url('cambiar_clave')) ?>">Mi cuenta <?= icono('person') ?></a></div>
<?php if (!$historial): ?><div class="vacio">Aún no tienes préstamos devueltos.</div><?php else: ?>
<div class="tabla-envoltura"><table class="tabla"><thead><tr><th>Obra bibliográfica</th><th>Retiro</th><th>Devolución</th><th>Estado de retorno</th></tr></thead><tbody>
<?php foreach ($historial as $r): ?><tr><td><div class="material-celda"><?= ui_portada($r, 'portada-mini') ?><div><strong><?= e($r['titulo']) ?></strong><br><small><?= e($r['autor']) ?></small></div></div></td><td><?= fecha_es($r['fecha_prestamo']) ?></td><td><?= fecha_es($r['estado'] === 'repuesto' ? $r['fecha_reposicion'] : $r['fecha_devolucion']) ?></td><td><span class="estado <?= $r['estado'] === 'repuesto' ? 'est-gris' : 'est-verde' ?>"><?= $r['estado'] === 'repuesto' ? 'Repuesto' : 'Devuelto' ?></span></td></tr><?php endforeach; ?>
</tbody></table></div>
<?= enlaces_paginacion($total, $limite, $pag, 'mis_prestamos', []) ?>
<?php endif; ?>
<div class="ayuda-panel"><h3><?= icono('shield') ?> Expediente de lecturas</h3><p>Este historial presenta los préstamos y devoluciones registrados por el personal de la Biblioteca Escolar I.E. San Juan.</p></div>
</section>
<?php pagina_fin();
