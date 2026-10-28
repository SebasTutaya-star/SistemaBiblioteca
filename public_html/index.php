<?php
/**
 * Controlador frontal: todas las páginas pasan por aquí (index.php?p=nombre).
 * Cada página declara el permiso requerido; se verifica en el servidor (RNF-01).
 */
require __DIR__ . '/app/bootstrap.php';

// página => [archivo en /paginas, permiso requerido (null = cualquier usuario con sesión)]
$rutas = [
    'inicio'            => ['inicio.php', null],
    'cambiar_clave'     => ['cambiar_clave.php', null],
    'salir'             => ['salir.php', null],
    // Catálogo (RF-04 a RF-10)
    'catalogo'          => ['catalogo.php', 'catalogo.consultar'],
    'material_ver'      => ['material_ver.php', 'catalogo.consultar'],
    'material_form'     => ['material_form.php', 'catalogo.gestionar'],
    'material_estado'   => ['material_estado.php', 'catalogo.gestionar'],
    'api_codigo'        => ['api_codigo.php', 'catalogo.gestionar'],
    // Préstamos (RF-11 a RF-18)
    'prestamos'         => ['prestamos.php', 'prestamos.gestionar'],
    'prestamo_nuevo'    => ['prestamo_nuevo.php', 'prestamos.gestionar'],
    'devolucion'        => ['devolucion.php', 'prestamos.gestionar'],
    'renovacion'        => ['renovacion.php', 'prestamos.renovar'],
    'reposicion'        => ['reposicion.php', 'prestamos.gestionar'],
    'api_buscar'        => ['api_buscar.php', 'prestamos.gestionar'],
    'historial'         => ['historial.php', 'historial.general'],
    'mis_prestamos'     => ['mis_prestamos.php', 'historial.propio'],
    'reglas'            => ['reglas.php', 'prestamos.reglas'],
    // Alertas y reportes (RF-19, RF-20)
    'alertas'           => ['alertas.php', 'alertas.ver'],
    'reportes'          => ['reportes.php', 'reportes.generar'],
    // Importación (RF-21)
    'importar'          => ['importar.php', 'catalogo.importar'],
    'plantilla'         => ['plantilla.php', null],
    // Administración (RF-02, RF-03)
    'usuarios'          => ['usuarios.php', 'usuarios.gestionar'],
    'usuario_form'      => ['usuario_form.php', 'usuarios.gestionar'],
    'padron'            => ['padron.php', 'usuarios.gestionar'],
    'roles'             => ['roles.php', 'usuarios.gestionar'],
];
$publicas = ['login', 'recuperar', 'restablecer'];

$pagina = preg_replace('/[^a-z_]/', '', (string)($_GET['p'] ?? ''));

if (in_array($pagina, $publicas, true)) {
    require RAIZ . "/paginas/$pagina.php";
    exit;
}

$u = usuario();
if (!$u) {
    redirigir('login');
}

// Cambio obligatorio de la contraseña inicial (DNI)
if ($u['debe_cambiar_clave'] && !empty($GLOBALS['CONFIG']['forzar_cambio_clave_inicial'])
    && !in_array($pagina, ['cambiar_clave', 'salir'], true)) {
    redirigir('cambiar_clave');
}

if ($pagina === '' || !isset($rutas[$pagina])) {
    $pagina = 'inicio';
}
[$archivo, $permiso] = $rutas[$pagina];
if ($permiso) {
    exigir($permiso);
}
require RAIZ . '/paginas/' . $archivo;
