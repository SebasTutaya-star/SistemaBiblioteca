<?php
/** Vista del panel. Solo consultas de lectura mediante los helpers existentes. */
pagina_inicio('Panel de control', 'inicio');
if ($u['rol'] === 'lector'):
    $situacion = situacion_lector((int)$u['id']);
    $limite = limite_lector($u);
    $lecturas = filas("SELECT p.*, m.codigo, m.titulo, m.autor FROM prestamos p JOIN materiales m ON m.id=p.material_id WHERE p.lector_id=? AND p.estado IN ('activo','perdido') ORDER BY p.fecha_vencimiento", [$u['id']]);
    $seleccion = filas(sql_materiales() . ' WHERE m.activo=1 ORDER BY m.id DESC LIMIT 4');
?>
<?php if ($u['debe_cambiar_clave']): ?><div class="aviso aviso-advertencia"><?= icono('shield') ?> Está usando su contraseña inicial. <a href="<?= e(url('cambiar_clave')) ?>">Cambiar contraseña</a></div><?php endif; ?>
<div class="panel-columnas">
  <section class="bienvenida">
    <div class="sobrelinea"><span class="punto"></span><?= e(ucfirst($u['tipo_lector'])) ?> · Cuenta activa <span class="etiqueta" style="margin-left:auto">DNI: <?= e($u['dni']) ?></span></div>
    <h1>¡Hola, <?= e($u['nombres']) ?>!</h1>
    <p class="sub"><?= e($u['grado'] ? $u['grado'] . ' · Sección ' . $u['seccion'] . ' · ' : '') ?>Periodo académico <?= e(conf('periodo_actual', date('Y'))) ?></p>
    <div class="cupo"><div class="cupo-top"><span><?= icono('book') ?> Cupo de préstamos</span><span><?= $situacion['activos'] ?><?= $limite ? ' de ' . $limite : '' ?> libro(s)</span></div>
    <?php if ($limite): ?><progress value="<?= min($limite, $situacion['activos']) ?>" max="<?= $limite ?>" aria-label="Libros prestados respecto al cupo permitido"></progress><?php endif; ?>
    <p><?php if ($situacion['vencidos'] || $situacion['perdidos']): ?>Tienes materiales pendientes de regularización. Consulta tus préstamos y acércate a biblioteca.<?php elseif ($limite): ?>Tienes <?= max(0, $limite - $situacion['activos']) ?> cupo(s) disponible(s). Presenta tu identificación en el mostrador para solicitar tu próxima lectura.<?php else: ?>Tu perfil no tiene un límite fijo de préstamos. Consulta las condiciones del material con el personal.<?php endif; ?></p></div>
    <div class="bienvenida-links"><a class="btn" href="<?= e(url('catalogo')) ?>"><?= icono('search') ?> Buscar en catálogo</a><a class="btn" href="<?= e(url('mis_prestamos')) ?>"><?= icono('book') ?> Mis préstamos</a><a class="btn" href="<?= e(url('mis_prestamos')) ?>#historial"><?= icono('clock') ?> Mi historial</a></div>
  </section>
  <aside class="reglamento"><div class="sobrelinea"><?= icono('shield') ?> Normativa sanjuanista</div><h2>Reglamento de préstamos escolares</h2><ul>
    <li><?= icono('check') ?><div><strong><?= $limite ? 'Máximo ' . $limite . ' libro(s) simultáneos' : 'Condiciones del perfil docente' ?></strong><small>Los plazos dependen del tipo de material y se confirman al registrar el préstamo.</small></div></li>
    <li><?= icono('clock') ?><div><strong>Puntualidad en devoluciones</strong><small>Entrega tus materiales antes del vencimiento. <?= conf('bloquear_con_vencidos', '1') === '1' ? 'Los préstamos vencidos bloquean nuevos retiros.' : 'Consulta con el personal si necesitas más tiempo.' ?></small></div></li>
    <li><?= icono('book') ?><div><strong>Cuidado y preservación</strong><small>Conserva los materiales en buen estado. Las pérdidas requieren reposición.</small></div></li>
  </ul></aside>
</div>
<div class="seccion-titulo"><h2><?= icono('book') ?> Préstamos en curso</h2><a class="btn btn-chico" href="<?= e(url('mis_prestamos')) ?>">Ver todos <?= icono('arrow') ?></a></div>
<?php if (!$lecturas): ?><div class="vacio">Aún no tienes materiales prestados. <a href="<?= e(url('catalogo')) ?>">Explora el catálogo</a> para encontrar tu próxima lectura.</div><?php endif; ?>
<?php foreach (array_slice($lecturas, 0, 2) as $r): ?>
<article class="tarjeta libro-activo"><?= ui_portada($r) ?><div class="libro-activo-info"><div class="ficha-cab"><span class="sobrelinea" style="margin:0">Lectura en curso · <?= e($r['codigo']) ?></span><?= estado_prestamo_html($r) ?></div><h3><?= e($r['titulo']) ?></h3><p class="sub"><?= e($r['autor']) ?></p><div class="fechas-prestamo"><div>Fecha de retiro<strong><?= fecha_es($r['fecha_prestamo']) ?></strong></div><div>Fecha límite de devolución<strong><?= fecha_es($r['fecha_vencimiento']) ?></strong></div></div><div class="ayuda-panel">¿Necesitas extender el plazo? Acércate al personal de biblioteca antes del vencimiento para solicitar una renovación.</div></div></article>
<?php endforeach; ?>
<div class="seccion-titulo"><h2>Explora nuevas lecturas</h2><a class="btn btn-primario" href="<?= e(url('catalogo')) ?>">Explorar catálogo <?= icono('arrow') ?></a></div>
<div class="fichas"><?php foreach ($seleccion as $m): ?><article class="ficha"><?= ui_portada($m) ?><h3><a href="<?= e(url('material_ver', ['id'=>$m['id']])) ?>"><?= e($m['titulo']) ?></a></h3><p class="autor"><?= e($m['autor']) ?></p><p style="margin-top:12px"><?= disponibilidad_html($m) ?></p><a class="btn" href="<?= e(url('material_ver', ['id'=>$m['id']])) ?>">Ver ficha <?= icono('arrow') ?></a></article><?php endforeach; ?></div>
<?php elseif (puede('catalogo.gestionar')):
    encabezado('Panel de Control General', 'Gestión de circulación, inventario y seguimiento de la biblioteca.');
?>
<div class="accesos"><?php foreach (array_slice($accesos, 0, 4) as [$pag,$tit,$perm,$ico,$desc]): if (!puede($perm)) continue; ?><a class="acceso" href="<?= e(url($pag)) ?>"><span class="acceso-ico"><?= icono($ico) ?></span><span><strong><?= e($tit) ?></strong><small><?= e($desc) ?></small></span></a><?php endforeach; ?></div>
<?php
    $inventario = fila('SELECT COUNT(*) titulos, COALESCE(SUM(cantidad_total),0) ejemplares, COALESCE(SUM(disponibles),0) disponibles, COALESCE(SUM(prestados),0) prestados FROM (' . sql_materiales() . ' WHERE m.activo=1) inventario');
    $seguimiento = filas("SELECT p.*, m.codigo, m.titulo, u.nombres, u.apellidos, u.grado, u.seccion FROM prestamos p JOIN materiales m ON m.id=p.material_id JOIN usuarios u ON u.id=p.lector_id WHERE p.estado='activo' ORDER BY p.fecha_vencimiento LIMIT 6");
    $escasos = filas(sql_materiales() . ' WHERE m.activo=1 HAVING disponibles <= 1 ORDER BY disponibles, m.titulo LIMIT 4');
    $meses = filas("SELECT MONTH(fecha_prestamo) mes, COUNT(*) n FROM prestamos WHERE YEAR(fecha_prestamo)=YEAR(CURDATE()) GROUP BY MONTH(fecha_prestamo)");
    $devoluciones = filas("SELECT MONTH(fecha_devolucion) mes, COUNT(*) n FROM prestamos WHERE estado IN ('devuelto','repuesto') AND YEAR(fecha_devolucion)=YEAR(CURDATE()) GROUP BY MONTH(fecha_devolucion)");
    $areas = filas('SELECT COALESCE(NULLIF(area,\'\'),\'General\') area, COUNT(*) n FROM materiales WHERE activo=1 GROUP BY area ORDER BY n DESC LIMIT 5');
?>
<div class="metricas"><?php ui_metrica('Títulos registrados', number_format((int)$inventario['titulos']), 'Material bibliográfico activo', 'book'); ui_metrica('Existencias', number_format((int)$inventario['ejemplares']), 'Ejemplares del inventario', 'shelf', 'naranja'); ui_metrica('Disponibles', number_format((int)$inventario['disponibles']), 'En biblioteca para préstamo', 'check', 'verde'); ui_metrica('En circulación', number_format((int)$inventario['prestados']), 'Préstamos activos', 'swap'); ?></div>
<div class="panel-columnas">
  <section class="tarjeta"><h2>Flujo mensual de circulación bibliográfica</h2><p class="sub">Préstamos y devoluciones durante <?= date('Y') ?></p><div class="leyenda" style="margin-top:16px"><span><i></i>Préstamos</span><span><i></i>Devoluciones</span></div>
  <?php $prestamosMes = array_column($meses, 'n', 'mes'); $devolucionesMes = array_column($devoluciones, 'n', 'mes'); $maximo = max(1, ...array_values($prestamosMes), ...array_values($devolucionesMes)); $nombresMes=['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic']; ?>
  <div class="grafico-barras" role="img" aria-label="Gráfico de préstamos y devoluciones mensuales. Detalle numérico en la tabla siguiente."><?php for ($i=1;$i<=12;$i++): ?><div class="barra-grupo" title="<?= $nombresMes[$i-1] ?>: <?= (int)($prestamosMes[$i]??0) ?> préstamos, <?= (int)($devolucionesMes[$i]??0) ?> devoluciones"><i style="height:<?= round(($prestamosMes[$i]??0)/$maximo*100) ?>%"></i><i style="height:<?= round(($devolucionesMes[$i]??0)/$maximo*100) ?>%"></i><small><?= $nombresMes[$i-1] ?></small></div><?php endfor; ?></div>
  <details><summary>Ver datos de circulación</summary><div class="tabla-envoltura"><table class="tabla"><thead><tr><th>Mes</th><th>Préstamos</th><th>Devoluciones</th></tr></thead><tbody><?php for($i=1;$i<=12;$i++): ?><tr><td><?= $nombresMes[$i-1] ?></td><td><?= (int)($prestamosMes[$i]??0) ?></td><td><?= (int)($devolucionesMes[$i]??0) ?></td></tr><?php endfor; ?></tbody></table></div></details>
  </section>
  <aside class="tarjeta"><h2>Distribución por áreas</h2><p class="sub">Títulos del catálogo activo</p><?php foreach ($areas as $a): ?><div class="area-barra"><div><span><?= e($a['area']) ?></span><strong><?= (int)$a['n'] ?></strong></div><progress value="<?= (int)$a['n'] ?>" max="<?= max(1,(int)$inventario['titulos']) ?>" aria-label="<?= e($a['area']) ?>: <?= (int)$a['n'] ?> títulos"></progress></div><?php endforeach; ?><a class="enlace-sec" href="<?= e(url('catalogo')) ?>">Ver catálogo completo <?= icono('arrow') ?></a></aside>
</div>
<div class="panel-columnas"><section class="tarjeta"><div class="seccion-titulo" style="margin-top:0"><h2>Préstamos en seguimiento</h2><a class="btn btn-chico" href="<?= e(url('prestamos')) ?>">Ver todos</a></div><?php if (!$seguimiento): ?><p class="vacio">No hay préstamos activos.</p><?php else: ?><div class="tabla-envoltura"><table class="tabla"><thead><tr><th>Lector</th><th>Material</th><th>Vencimiento</th><th>Estado</th><th>Acción</th></tr></thead><tbody><?php foreach ($seguimiento as $r): ?><tr><td><strong><?= e(nombre_completo($r)) ?></strong><br><small><?= e(trim($r['grado'].' '.$r['seccion'])) ?></small></td><td><?= e($r['titulo']) ?><br><small><?= e($r['codigo']) ?></small></td><td><?= fecha_es($r['fecha_vencimiento']) ?></td><td><?= estado_prestamo_html($r) ?></td><td class="acciones-fila"><a class="btn btn-chico" href="<?= e(url('devolucion',['id'=>$r['id']])) ?>">Recibir</a></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
<aside class="tarjeta"><h2><?= icono('shelf') ?> Escasez de ejemplares</h2><p class="sub">Materiales con uno o ningún ejemplar disponible.</p><?php if (!$escasos): ?><p class="vacio">No hay materiales con stock crítico.</p><?php endif; ?><?php foreach($escasos as $m): ?><div class="area-barra"><strong><?= e($m['titulo']) ?></strong><div style="margin-top:6px"><?= disponibilidad_html($m) ?><a href="<?= e(url('material_ver',['id'=>$m['id']])) ?>">Ver ficha</a></div></div><?php endforeach; ?></aside></div>
<?php else:
    encabezado('Panel de administración', 'Cuentas, roles y padrón de la comunidad educativa.');
    $cuentas = filas('SELECT rol, COUNT(*) n FROM usuarios WHERE activo=1 GROUP BY rol');
    $conteo = array_column($cuentas,'n','rol');
?><div class="metricas"><?php foreach (ROLES as $rol=>$nombre) ui_metrica($nombre, (int)($conteo[$rol]??0), 'Cuentas activas', 'users'); ?></div>
<div class="accesos"><?php foreach ($accesos as [$pag,$tit,$perm,$ico,$desc]): if (!puede($perm)) continue; ?><a class="acceso" href="<?= e(url($pag)) ?>"><span class="acceso-ico"><?= icono($ico) ?></span><span><strong><?= e($tit) ?></strong><small><?= e($desc) ?></small></span></a><?php endforeach; ?><a class="acceso" href="<?= e(url('roles')) ?>"><span class="acceso-ico"><?= icono('shield') ?></span><span><strong>Roles y permisos</strong><small>Consulta las funciones asignadas a cada perfil.</small></span></a></div>
<section class="tarjeta" style="margin-top:24px"><h2><?= icono('shield') ?> Administración de la comunidad escolar</h2><p class="sub">Gestiona las cuentas desde Usuarios, actualiza el padrón del periodo académico y revisa los permisos de cada rol.</p><div class="bienvenida-links"><a class="btn" href="<?= e(url('usuarios')) ?>">Gestionar usuarios</a><a class="btn" href="<?= e(url('padron')) ?>">Actualizar padrón anual</a><a class="btn" href="<?= e(url('roles')) ?>">Consultar permisos</a></div></section>
<?php endif; pagina_fin();
