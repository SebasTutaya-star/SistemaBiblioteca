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
require __DIR__ . '/vistas/panel.php';
