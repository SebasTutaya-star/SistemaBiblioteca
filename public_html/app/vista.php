<?php
/**
 * Plantilla general: cabecera, menú lateral y pie.
 */

function icono(string $n): string
{
    $p = [
        'home'    => '<path d="M3 11l9-8 9 8"/><path d="M5 10v10h14V10"/>',
        'book'    => '<path d="M4 4h10a4 4 0 014 4v12H8a4 4 0 01-4-4z"/><path d="M4 16a4 4 0 014-4h10"/>',
        'swap'    => '<path d="M7 7h13l-4-4"/><path d="M17 17H4l4 4"/>',
        'list'    => '<path d="M9 6h11M9 12h11M9 18h11"/><circle cx="4" cy="6" r="1"/><circle cx="4" cy="12" r="1"/><circle cx="4" cy="18" r="1"/>',
        'clock'   => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/>',
        'bell'    => '<path d="M6 16V11a6 6 0 0112 0v5l2 2H4z"/><path d="M10 20a2 2 0 004 0"/>',
        'chart'   => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        'upload'  => '<path d="M12 16V4M7 9l5-5 5 5"/><path d="M4 16v4h16v-4"/>',
        'sliders' => '<path d="M4 6h10M18 6h2M4 12h4M12 12h8M4 18h12M20 18h0"/><circle cx="16" cy="6" r="2"/><circle cx="10" cy="12" r="2"/><circle cx="18" cy="18" r="2"/>',
        'users'   => '<circle cx="9" cy="8" r="3.5"/><path d="M2 20a7 7 0 0114 0"/><path d="M16 4.5a3.5 3.5 0 010 7M22 20a7 7 0 00-4-6.3"/>',
        'shield'  => '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/>',
        'key'     => '<circle cx="8" cy="15" r="4"/><path d="M11 12l9-9M17 6l3 3"/>',
        'logout'  => '<path d="M15 4h4v16h-4"/><path d="M10 8l-4 4 4 4M6 12h11"/>',
    ][$n] ?? '';
    return '<svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
}

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
    ?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($titulo) ?> · <?= e($app) ?></title>
<link rel="stylesheet" href="assets/css/estilos.css?v=1">
</head>
<body>
<?php if ($u): ?>
<input type="checkbox" id="menu-toggle" class="menu-toggle" aria-hidden="true">
<header class="barra-movil">
  <label for="menu-toggle" class="btn-menu" aria-label="Abrir menú">☰</label>
  <span class="marca-movil"><?= e($app) ?></span>
</header>
<aside class="lateral">
  <div class="marca">
    <div class="marca-sello">SJ</div>
    <div><strong>Biblioteca</strong><small>I.E.P. San Juan</small></div>
  </div>
  <nav class="menu">
    <?php foreach (menu() as [$pag, $etq, $perm, $ico]):
        if ($perm && !puede($perm)) continue;
        $badge = '';
        if ($pag === 'alertas') { $n = contar_alertas(); if ($n) $badge = '<span class="badge">' . $n . '</span>'; }
    ?>
      <a href="<?= e(url($pag)) ?>" class="<?= $actual === $pag ? 'activo' : '' ?>"><?= icono($ico) ?><span><?= e($etq) ?></span><?= $badge ?></a>
    <?php endforeach; ?>
  </nav>
  <div class="usuario-caja">
    <div class="usuario-nombre"><?= e(nombre_completo($u)) ?></div>
    <div class="usuario-rol"><?= e(ROLES[$u['rol']]) ?><?= $u['tipo_lector'] ? ' · ' . e(ucfirst($u['tipo_lector'])) : '' ?></div>
    <a href="<?= e(url('cambiar_clave')) ?>"><?= icono('key') ?> Cambiar contraseña</a>
    <form method="post" action="<?= e(url('salir')) ?>"><?= csrf_campo() ?><button class="enlace"><?= icono('logout') ?> Cerrar sesión</button></form>
  </div>
</aside>
<label for="menu-toggle" class="velo" aria-hidden="true"></label>
<main class="contenido">
<?php else: ?>
<main class="contenido-solo">
<?php endif; ?>
<?php foreach (tomar_flash() as $f): ?>
  <div class="aviso aviso-<?= e($f['tipo']) ?>"><?= e($f['msg']) ?></div>
<?php endforeach;
}

function pagina_fin(): void
{
    ?>
</main>
<script src="assets/js/app.js?v=1"></script>
</body>
</html>
<?php
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
