<?php
/**
 * Matriz de permisos por rol (RF-02, RNF-01).
 * Para cambiar qué puede hacer un rol, modifique solo esta tabla.
 */

const ROLES = [
    'admin'         => 'Administrador',
    'bibliotecaria' => 'Bibliotecaria',
    'auxiliar'      => 'Auxiliar',
    'lector'        => 'Lector',
];

const PERMISOS = [
    // permiso                => [roles que lo tienen, descripción, RF]
    'catalogo.consultar'   => [['bibliotecaria', 'auxiliar', 'lector'], 'Consultar y buscar en el catálogo', 'RF-09'],
    'catalogo.gestionar'   => [['bibliotecaria', 'auxiliar'], 'Registrar, editar, codificar materiales y gestionar cantidades/estados; ver ubicación física', 'RF-04 a RF-08, RF-10'],
    'catalogo.importar'    => [['bibliotecaria'], 'Importar el catálogo desde Excel', 'RF-21'],
    'prestamos.gestionar'  => [['bibliotecaria', 'auxiliar'], 'Registrar préstamos y devoluciones', 'RF-11, RF-12, RF-14, RF-16'],
    'prestamos.renovar'    => [['bibliotecaria', 'auxiliar'], 'Autorizar renovaciones con justificación', 'RF-15'],
    'prestamos.reglas'     => [['bibliotecaria'], 'Configurar reglas y periodos de préstamo', 'RF-13'],
    'historial.general'    => [['bibliotecaria', 'auxiliar'], 'Consultar préstamos e historial de todos los lectores', 'RF-18'],
    'historial.propio'     => [['lector'], 'Consultar sus propios préstamos e historial', 'RF-18'],
    'alertas.ver'          => [['bibliotecaria', 'auxiliar'], 'Consultar alertas de vencimientos y pendientes', 'RF-19'],
    'reportes.generar'     => [['bibliotecaria'], 'Generar y exportar reportes (Excel/PDF)', 'RF-20'],
    'usuarios.gestionar'   => [['admin'], 'Gestionar cuentas, roles y padrón de lectores', 'RF-02, RF-03'],
];

function puede(string $permiso, ?array $u = null): bool
{
    $u = $u ?? usuario();
    if (!$u || !isset(PERMISOS[$permiso])) return false;
    return in_array($u['rol'], PERMISOS[$permiso][0], true);
}

function exigir(string $permiso): void
{
    if (!puede($permiso)) {
        http_response_code(403);
        pagina_inicio('Acceso no permitido');
        echo '<div class="aviso aviso-error">Su cuenta no tiene permiso para usar esta opción.</div>';
        pagina_fin();
        exit;
    }
}

/** Menú lateral: [página, etiqueta, permiso, icono] */
function menu(): array
{
    return [
        ['inicio',         'Inicio',               null,                  'home'],
        ['catalogo',       'Catálogo',             'catalogo.consultar',  'book'],
        ['prestamos',      'Préstamos',            'prestamos.gestionar', 'swap'],
        ['mis_prestamos',  'Mis préstamos',        'historial.propio',    'list'],
        ['historial',      'Historial',            'historial.general',   'clock'],
        ['alertas',        'Alertas',              'alertas.ver',         'bell'],
        ['reportes',       'Reportes',             'reportes.generar',    'chart'],
        ['importar',       'Importar catálogo',    'catalogo.importar',   'upload'],
        ['reglas',         'Reglas de préstamo',   'prestamos.reglas',    'sliders'],
        ['usuarios',       'Usuarios',             'usuarios.gestionar',  'users'],
        ['padron',         'Padrón anual',         'usuarios.gestionar',  'upload'],
        ['roles',          'Roles y permisos',     'usuarios.gestionar',  'shield'],
    ];
}
