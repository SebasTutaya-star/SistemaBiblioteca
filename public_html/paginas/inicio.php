<?php
// Pantalla de inicio: accesos rápidos según el rol, sin panel de estadísticas obligatorio.
$u = usuario();
$accesos = [
    ['prestamo_nuevo', 'Registrar préstamo',  'prestamos.gestionar', 'swap',   'Prestar un material a un lector.'],
    ['prestamos',      'Registrar devolución','prestamos.gestionar', 'list',   'Ver préstamos activos y recibir libros.'],
    ['catalogo',       'Buscar en el catálogo','catalogo.consultar', 'book',   'Buscar por título, autor, tema, área, nivel o código.'],
    ['material_form',  'Registrar material',  'catalogo.gestionar',  'upload', 'Agregar un libro nuevo al catálogo.'],
    ['mis_prestamos',  'Mis préstamos',       'historial.propio',    'list',   'Ver mis préstamos, fechas de devolución e historial.'],
    ['alertas',        'Alertas',             'alertas.ver',         'bell',   'Vencidos, próximos a vencer y pendientes.'],
    ['reportes',       'Reportes',            'reportes.generar',    'chart',  'Reportes mensuales y anuales en Excel o PDF.'],
    ['usuarios',       'Usuarios',            'usuarios.gestionar',  'users',  'Cuentas, roles y estado de los usuarios.'],
    ['padron',         'Padrón anual',        'usuarios.gestionar',  'upload', 'Actualizar estudiantes y docentes del periodo.'],
];
pagina_inicio('Inicio', 'inicio');
encabezado('Hola, ' . $u['nombres'], '¿Qué desea hacer hoy?');

if (puede('historial.propio')) {
    $vencidos = (int)valor("SELECT COUNT(*) FROM prestamos WHERE lector_id = ? AND estado = 'activo' AND fecha_vencimiento < CURDATE()", [$u['id']]);
    if ($vencidos) {
        echo '<div class="aviso aviso-error">Tiene ' . $vencidos . ' préstamo(s) vencido(s). Acérquese a la biblioteca para devolverlo(s). <a href="' . e(url('mis_prestamos')) . '">Ver detalle</a></div>';
    }
}
?>
<div class="accesos">
<?php foreach ($accesos as [$pag, $tit, $perm, $ico, $desc]): if (!puede($perm)) continue; ?>
  <a class="acceso" href="<?= e(url($pag)) ?>">
    <span class="acceso-ico"><?= icono($ico) ?></span>
    <strong><?= e($tit) ?></strong>
    <small><?= e($desc) ?></small>
  </a>
<?php endforeach; ?>
</div>
<?php pagina_fin();
