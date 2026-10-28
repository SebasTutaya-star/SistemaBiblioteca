<?php
/** Marco visual compartido de los prototipos Stitch. */
require_once __DIR__ . '/ui.php';
function icono(string $n): string { return ui_icono($n); }
function contar_alertas(): int
{
    $dias = (int)conf('dias_alerta_proximo', 2);
    return (int)valor("SELECT COUNT(*) FROM prestamos WHERE estado = 'activo' AND fecha_vencimiento <= DATE_ADD(CURDATE(), INTERVAL ? DAY)", [$dias])
         + (int)valor("SELECT COUNT(*) FROM prestamos WHERE estado = 'perdido'");
}
function pagina_inicio(string $titulo, string $actual = ''): void
{
    $u = usuario();
    $app = $GLOBALS['CONFIG']['app_nombre'] ?? 'Biblioteca';
    $lector = $u && $u['rol'] === 'lector';
    $rol = $u ? ($lector ? ucfirst((string)$u['tipo_lector']) : ROLES[$u['rol']]) : '';
    $nav = array_values(array_filter(menu(), fn($item) => !$item[2] || puede($item[2])));
    $GLOBALS['UI_ACTUAL'] = $actual;
?><!doctype html>
<html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#1e3a8a">
<title><?= e($titulo) ?> · <?= e($app) ?></title>
<link rel="icon" href="assets/img/insignia-san-juan.png">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Material+Symbols+Outlined:wght,FILL@400,0&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/estilos.css?v=stitch-1">
</head><body class="<?= $u ? ($lector ? 'sesion lector' : 'sesion personal') : 'sin-sesion' ?>" data-pagina="<?= e(g('p', 'inicio')) ?>">
<a class="saltar-contenido" href="#contenido">Ir al contenido</a>
<?php if ($u): ?>
<header class="cabecera-app">
  <button type="button" class="icon-btn abrir-menu" aria-controls="menu-lateral" aria-expanded="false" aria-label="Abrir menú"><?= icono('menu') ?></button>
  <a class="cabecera-marca" href="<?= e(url('inicio')) ?>"><img src="assets/img/insignia-san-juan.png" alt="Insignia San Juan"><span><strong>Biblioteca Escolar</strong><small>I.E. San Juan</small></span></a>
  <span class="rol-chip"><?= e($rol) ?></span>
  <div class="cabecera-derecha">
    <?php if (puede('alertas.ver')): $n = contar_alertas(); ?><a class="icon-btn notificacion" href="<?= e(url('alertas')) ?>" aria-label="Ver alertas<?= $n ? ': ' . $n . ' pendientes' : '' ?>"><?= icono('bell') ?><?php if ($n): ?><b><?= $n ?></b><?php endif; ?></a><?php endif; ?>
    <a class="perfil-enlace" href="<?= e(url('cambiar_clave')) ?>"><span><strong><?= e(nombre_completo($u)) ?></strong><small><?= e($u['grado'] ? $u['grado'] . ' · ' . $u['seccion'] : $rol) ?></small></span><span class="avatar"><?= e(mb_substr($u['nombres'], 0, 1) . mb_substr($u['apellidos'], 0, 1)) ?></span></a>
  </div>
</header>
<aside class="lateral" id="menu-lateral" aria-label="Navegación principal">
  <a class="marca" href="<?= e(url('inicio')) ?>"><img src="assets/img/insignia-san-juan.png" alt=""><span><strong>I.E. San Juan</strong><small><?= $lector ? 'Portal del lector' : 'Sistema de biblioteca' ?></small></span></a>
  <div class="contexto-rol"><span class="punto"></span><div><small><?= $lector ? 'Nivel académico' : 'Sesión activa' ?></small><strong><?= e($u['grado'] ? $u['grado'] . ' ' . $u['seccion'] : $rol) ?></strong></div></div>
  <nav class="menu">
    <span class="menu-seccion"><?= $lector ? 'Mi biblioteca' : 'Biblioteca escolar' ?></span>
    <?php foreach ($nav as [$pag, $etq, $perm, $ico]): ?><a href="<?= e(url($pag)) ?>" class="<?= $actual === $pag ? 'activo' : '' ?>" <?= $actual === $pag ? 'aria-current="page"' : '' ?>><?= icono($ico) ?><span><?= e($etq) ?></span></a><?php endforeach; ?>
    <span class="menu-seccion">Mi cuenta</span>
    <a href="<?= e(url('cambiar_clave')) ?>" <?= g('p') === 'cambiar_clave' ? 'aria-current="page" class="activo"' : '' ?>><?= icono('person') ?><span>Mi cuenta y contraseña</span></a>
  </nav>
  <div class="usuario-caja"><span class="estado-conexion"><span class="punto"></span>Biblioteca I.E. San Juan</span><small>San Juan Bautista · Ayacucho</small><form method="post" action="<?= e(url('salir')) ?>"><?= csrf_campo() ?><button class="cerrar-sesion"><?= icono('logout') ?>Cerrar sesión</button></form></div>
</aside>
<button class="velo" type="button" aria-label="Cerrar menú" hidden></button>
<main class="contenido" id="contenido">
<div class="ruta-contexto"><span><?= icono('home') ?> I.E. San Juan</span><span>/</span><span><?= e($titulo) ?></span><time datetime="<?= date('Y-m-d') ?>"><?= fecha_es(hoy()) ?></time></div>
<?php else: ui_cabecera_auth(); ?><main class="contenido-solo" id="contenido">
<?php endif; ?>
<?php foreach (tomar_flash() as $f): ?><div class="aviso aviso-<?= e($f['tipo']) ?>" role="status"><?= e($f['msg']) ?></div><?php endforeach;
}
function pagina_fin(): void
{
    $u = usuario();
    $actual = $GLOBALS['UI_ACTUAL'] ?? '';
?></main>
<?php if ($u):
    $items = [['inicio','Inicio','home',null],['catalogo','Catálogo','book','catalogo.consultar']];
    if ($u['rol'] === 'lector') { $items[] = ['mis_prestamos','Préstamos','list','historial.propio']; $items[] = ['mis_prestamos#historial','Historial','clock','historial.propio']; }
    elseif (puede('prestamos.gestionar')) { $items[] = ['prestamos','Préstamos','swap','prestamos.gestionar']; $items[] = ['historial','Historial','clock','historial.general']; }
    elseif (puede('usuarios.gestionar')) { $items[] = ['usuarios','Usuarios','users','usuarios.gestionar']; $items[] = ['padron','Padrón','upload','usuarios.gestionar']; }
?>
<nav class="nav-inferior" aria-label="Navegación móvil">
<?php foreach ($items as [$pag, $etq, $ico, $perm]): if ($perm && !puede($perm)) continue; $partes = explode('#', $pag); ?><a href="<?= e(url($partes[0]) . (isset($partes[1]) ? '#' . $partes[1] : '')) ?>" class="<?= $actual === $partes[0] && !isset($partes[1]) ? 'activo' : '' ?>"><?= icono($ico) ?><span><?= e($etq) ?></span></a><?php endforeach; ?>
<button type="button" class="abrir-menu" aria-label="Más opciones" aria-controls="menu-lateral" aria-expanded="false"><?= icono('more_horiz') ?><span>Más</span></button>
</nav>
<footer class="pie-app"><span>Biblioteca Escolar · I.E. San Juan</span><span><?= date('Y') ?> · San Juan Bautista, Ayacucho</span></footer>
<?php else: ui_pie_auth(); endif; ?>
<script src="assets/js/app.js?v=stitch-1" defer></script>
<script src="assets/js/interfaz.js?v=stitch-1" defer></script>
</body></html><?php
}
/** Cabecera de sección con título y acciones opcionales (HTML ya escapado). */
function encabezado(string $titulo, string $sub = '', string $acciones = ''): void
{
    echo '<div class="encabezado"><div><h1>' . e($titulo) . '</h1>';
    if ($sub) echo '<p class="sub">' . e($sub) . '</p>';
    echo '</div>' . ($acciones ? '<div class="acciones">' . $acciones . '</div>' : '') . '</div>';
}

function disponibilidad_html(array $m): string
{
    if (!$m['activo']) return '<span class="estado est-gris">Inactivo</span>';
    $d = (int)$m['disponibles'];
    if ($d === 0) return '<span class="estado est-rojo">No disponible</span>';
    if ($d === 1) return '<span class="estado est-ambar">1 disponible</span>';
    return '<span class="estado est-verde">' . $d . ' disponibles</span>';
}

function estado_prestamo_html(array $p): string
{
    switch ($p['estado']) {
        case 'devuelto': return '<span class="estado est-gris">Devuelto</span>';
        case 'perdido':  return '<span class="estado est-rojo">Perdido · reposición pendiente</span>';
        case 'repuesto': return '<span class="estado est-gris">Perdido · repuesto</span>';
    }
    $dias = dias_entre(hoy(), $p['fecha_vencimiento']);
    if ($dias < 0) return '<span class="estado est-rojo">Vencido hace ' . abs($dias) . ' d</span>';
    if ($dias <= (int)conf('dias_alerta_proximo', 2)) return '<span class="estado est-ambar">' . ($dias === 0 ? 'Vence hoy' : "Vence en $dias d") . '</span>';
    return '<span class="estado est-verde">Vigente</span>';
}
