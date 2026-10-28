<?php
/**
 * Reglas de préstamo (RF-12, RF-13, RF-14).
 */

/** Fecha de vencimiento por defecto según el tipo de material (RF-13). */
function vencimiento_sugerido(array $material): string
{
    $tipo = $material['tipo_material_id']
        ? fila('SELECT * FROM tipos_material WHERE id = ?', [$material['tipo_material_id']])
        : null;
    if ($tipo && $tipo['hasta_fin_periodo']) {
        $fin = (string)conf('fin_periodo_academico', '');
        if (fecha_valida($fin) && $fin >= hoy()) return $fin;
    }
    $dias = $tipo ? max(1, (int)$tipo['dias_prestamo']) : 2;
    return date('Y-m-d', strtotime("+$dias days"));
}

/** Situación del Lector: préstamos activos, vencidos y perdidos sin reponer. */
function situacion_lector(int $lectorId): array
{
    $r = fila("SELECT SUM(estado = 'activo') AS activos,
                      SUM(estado = 'activo' AND fecha_vencimiento < CURDATE()) AS vencidos,
                      SUM(estado = 'perdido') AS perdidos
               FROM prestamos WHERE lector_id = ?", [$lectorId]);
    return ['activos' => (int)$r['activos'], 'vencidos' => (int)$r['vencidos'], 'perdidos' => (int)$r['perdidos']];
}

function limite_lector(array $lector): int
{
    return $lector['tipo_lector'] === 'docente'
        ? (int)conf('max_prestamos_docente', 0)
        : (int)conf('max_prestamos_estudiante', 2);
}

/**
 * Verifica disponibilidad del material y condiciones del Lector (RF-12, RF-13).
 * Devuelve ['errores' => [...], 'advertencias' => [...]].
 * Las advertencias no bloquean: el personal decide (RF-14).
 */
function validar_prestamo(?array $lector, ?array $material, string $vence): array
{
    $err = [];
    $adv = [];
    if (!$lector) $err[] = 'Seleccione un lector válido.';
    elseif ($lector['rol'] !== 'lector') $err[] = 'Solo se puede prestar a cuentas de Lector (estudiante o docente).';
    elseif (!$lector['activo']) $err[] = 'La cuenta del lector está desactivada.';

    if (!$material) $err[] = 'Seleccione un material válido.';
    elseif (!$material['activo']) $err[] = 'El material está inactivo y no puede prestarse.';
    elseif ((int)$material['disponibles'] < 1) $err[] = 'No hay ejemplares disponibles de este material.';
    elseif ((int)$material['disponibles'] === 1) {
        $adv[] = 'Queda un único ejemplar disponible de «' . $material['titulo'] . '». Confirme si desea prestarlo.';
    }

    if (!fecha_valida($vence)) $err[] = 'Indique una fecha de devolución válida.';
    elseif ($vence < hoy()) $err[] = 'La fecha de devolución no puede ser anterior a hoy.';

    if ($lector && $lector['rol'] === 'lector') {
        $s = situacion_lector((int)$lector['id']);
        if ($s['vencidos'] > 0 && conf('bloquear_con_vencidos', '1') === '1') {
            $err[] = 'El lector tiene ' . $s['vencidos'] . ' préstamo(s) vencido(s). Debe regularizar antes de un nuevo préstamo.';
        }
        if ($s['perdidos'] > 0 && conf('bloquear_con_perdidos', '1') === '1') {
            $err[] = 'El lector tiene ' . $s['perdidos'] . ' libro(s) perdido(s) pendiente(s) de reposición.';
        }
        $max = limite_lector($lector);
        if ($max > 0 && $s['activos'] >= $max) {
            $tipo = $lector['tipo_lector'] === 'docente' ? 'docentes' : 'estudiantes';
            $err[] = "El lector ya tiene {$s['activos']} préstamo(s) activo(s). El máximo para $tipo es $max.";
        }
        if ($material && valor("SELECT COUNT(*) FROM prestamos WHERE lector_id = ? AND material_id = ? AND estado = 'activo'", [$lector['id'], $material['id']])) {
            $adv[] = 'El lector ya tiene prestado un ejemplar de este mismo material.';
        }
    }
    return ['errores' => $err, 'advertencias' => $adv];
}

function prestamo_detalle(int $id): ?array
{
    return fila("SELECT p.*, m.codigo, m.titulo, m.autor, u.nombres, u.apellidos, u.dni, u.tipo_lector, u.grado, u.seccion
                 FROM prestamos p JOIN materiales m ON m.id = p.material_id JOIN usuarios u ON u.id = p.lector_id
                 WHERE p.id = ?", [$id]);
}
