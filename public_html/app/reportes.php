<?php
/**
 * Datos de los reportes (RF-20). Cada reporte devuelve título, columnas y filas,
 * que se usan igual para la vista en pantalla, Excel y PDF.
 * El sistema entrega datos; las conclusiones y recomendaciones las redacta la Bibliotecaria.
 */

const TIPOS_REPORTE = [
    'prestamos'   => 'Préstamos realizados',
    'devoluciones'=> 'Devoluciones registradas',
    'pendientes'  => 'Materiales pendientes de devolución',
    'deudores'    => 'Lectores deudores',
    'mensual'     => 'Resumen mensual',
    'anual'       => 'Resumen anual por meses',
];

const MESES = [1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Setiembre', 'Octubre', 'Noviembre', 'Diciembre'];

function lector_txt(array $r): string
{
    return $r['grado'] ? $r['grado'] . ' ' . $r['seccion'] : ucfirst((string)$r['tipo_lector']);
}

function generar_reporte(string $tipo, string $desde, string $hasta, int $mes, int $anio): array
{
    $rango = [$desde . ' 00:00:00', $hasta . ' 23:59:59'];
    $periodo = 'Del ' . fecha_es($desde) . ' al ' . fecha_es($hasta);

    switch ($tipo) {
        case 'prestamos':
            $rs = filas("SELECT p.*, m.codigo, m.titulo, m.area, u.nombres, u.apellidos, u.dni, u.tipo_lector, u.grado, u.seccion,
                                CONCAT(r.nombres,' ',r.apellidos) AS responsable
                         FROM prestamos p JOIN materiales m ON m.id = p.material_id JOIN usuarios u ON u.id = p.lector_id
                         JOIN usuarios r ON r.id = p.registrado_por
                         WHERE p.fecha_prestamo BETWEEN ? AND ? ORDER BY p.fecha_prestamo", $rango);
            $estados = ['activo' => 'En préstamo', 'devuelto' => 'Devuelto', 'perdido' => 'Perdido', 'repuesto' => 'Repuesto'];
            return ['titulo' => TIPOS_REPORTE[$tipo], 'periodo' => $periodo,
                'columnas' => ['Fecha y hora', 'Código', 'Título', 'Área', 'Lector', 'DNI', 'Grado/tipo', 'Vence', 'Estado', 'Registrado por'],
                'filas' => array_map(fn($r) => [fecha_es($r['fecha_prestamo'], true), $r['codigo'], $r['titulo'], (string)$r['area'],
                    nombre_completo($r), $r['dni'], lector_txt($r), fecha_es($r['fecha_vencimiento']), $estados[$r['estado']], $r['responsable']], $rs)];

        case 'devoluciones':
            $rs = filas("SELECT p.*, m.codigo, m.titulo, u.nombres, u.apellidos, u.dni, u.tipo_lector, u.grado, u.seccion,
                                CONCAT(r.nombres,' ',r.apellidos) AS responsable
                         FROM prestamos p JOIN materiales m ON m.id = p.material_id JOIN usuarios u ON u.id = p.lector_id
                         JOIN usuarios r ON r.id = p.recibido_por
                         WHERE p.estado IN ('devuelto','perdido','repuesto') AND p.fecha_devolucion BETWEEN ? AND ? ORDER BY p.fecha_devolucion", $rango);
            return ['titulo' => TIPOS_REPORTE[$tipo], 'periodo' => $periodo,
                'columnas' => ['Fecha y hora', 'Código', 'Título', 'Lector', 'Grado/tipo', 'Prestado', 'Vencía', 'Días de retraso', 'Condición', 'Recibido por'],
                'filas' => array_map(function ($r) {
                    $ret = max(0, dias_entre($r['fecha_vencimiento'], substr($r['fecha_devolucion'], 0, 10)));
                    $cond = $r['estado'] === 'devuelto' ? ($r['condicion_devolucion'] === 'deteriorado' ? 'Deteriorado' : 'Bueno') : 'Perdido';
                    return [fecha_es($r['fecha_devolucion'], true), $r['codigo'], $r['titulo'], nombre_completo($r), lector_txt($r),
                            fecha_es($r['fecha_prestamo']), fecha_es($r['fecha_vencimiento']), $ret, $cond, $r['responsable']];
                }, $rs)];

        case 'pendientes':
            $rs = filas("SELECT p.*, m.codigo, m.titulo, m.ubicacion, u.nombres, u.apellidos, u.dni, u.tipo_lector, u.grado, u.seccion
                         FROM prestamos p JOIN materiales m ON m.id = p.material_id JOIN usuarios u ON u.id = p.lector_id
                         WHERE p.estado = 'activo' ORDER BY p.fecha_vencimiento, u.grado, u.seccion");
            return ['titulo' => TIPOS_REPORTE[$tipo], 'periodo' => 'Situación al ' . fecha_es(date('Y-m-d H:i'), true),
                'columnas' => ['Código', 'Título', 'Lector', 'DNI', 'Grado/tipo', 'Prestado', 'Devolver hasta', 'Situación'],
                'filas' => array_map(function ($r) {
                    $d = dias_entre(hoy(), $r['fecha_vencimiento']);
                    return [$r['codigo'], $r['titulo'], nombre_completo($r), $r['dni'], lector_txt($r), fecha_es($r['fecha_prestamo']),
                            fecha_es($r['fecha_vencimiento']), $d < 0 ? 'Vencido hace ' . abs($d) . ' día(s)' : 'Vigente'];
                }, $rs)];

        case 'deudores':
            $rs = filas("SELECT u.nombres, u.apellidos, u.dni, u.tipo_lector, u.grado, u.seccion, u.correo,
                                SUM(p.estado = 'activo' AND p.fecha_vencimiento < CURDATE()) AS vencidos,
                                SUM(p.estado = 'perdido') AS perdidos,
                                MIN(CASE WHEN p.estado = 'activo' THEN p.fecha_vencimiento END) AS mas_antiguo,
                                GROUP_CONCAT(m.titulo SEPARATOR '; ') AS materiales
                         FROM prestamos p JOIN usuarios u ON u.id = p.lector_id JOIN materiales m ON m.id = p.material_id
                         WHERE (p.estado = 'activo' AND p.fecha_vencimiento < CURDATE()) OR p.estado = 'perdido'
                         GROUP BY u.id ORDER BY u.grado, u.seccion, u.apellidos");
            return ['titulo' => TIPOS_REPORTE[$tipo], 'periodo' => 'Lectores con préstamos vencidos o libros perdidos sin reponer, al ' . fecha_es(hoy()),
                'columnas' => ['Lector', 'DNI', 'Grado/tipo', 'Correo', 'Vencidos', 'Perdidos', 'Vencido desde', 'Materiales'],
                'filas' => array_map(fn($r) => [nombre_completo($r), $r['dni'], lector_txt($r), $r['correo'], (int)$r['vencidos'],
                    (int)$r['perdidos'], fecha_es($r['mas_antiguo']), $r['materiales']], $rs)];

        case 'mensual':
            $ini = sprintf('%04d-%02d-01', $anio, $mes);
            $fin = date('Y-m-t', strtotime($ini));
            $r = [$ini . ' 00:00:00', $fin . ' 23:59:59'];
            $filas = [
                ['Préstamos realizados', (int)valor('SELECT COUNT(*) FROM prestamos WHERE fecha_prestamo BETWEEN ? AND ?', $r)],
                ['   · a estudiantes', (int)valor("SELECT COUNT(*) FROM prestamos p JOIN usuarios u ON u.id = p.lector_id WHERE u.tipo_lector = 'estudiante' AND p.fecha_prestamo BETWEEN ? AND ?", $r)],
                ['   · a docentes', (int)valor("SELECT COUNT(*) FROM prestamos p JOIN usuarios u ON u.id = p.lector_id WHERE u.tipo_lector = 'docente' AND p.fecha_prestamo BETWEEN ? AND ?", $r)],
                ['Devoluciones registradas', (int)valor("SELECT COUNT(*) FROM prestamos WHERE estado IN ('devuelto','repuesto') AND fecha_devolucion BETWEEN ? AND ?", $r)],
                ['   · devueltas con retraso', (int)valor("SELECT COUNT(*) FROM prestamos WHERE estado IN ('devuelto','repuesto') AND fecha_devolucion BETWEEN ? AND ? AND DATE(fecha_devolucion) > fecha_vencimiento", $r)],
                ['   · devueltas deterioradas', (int)valor("SELECT COUNT(*) FROM prestamos WHERE condicion_devolucion = 'deteriorado' AND fecha_devolucion BETWEEN ? AND ?", $r)],
                ['Renovaciones autorizadas', (int)valor('SELECT COUNT(*) FROM renovaciones WHERE fecha BETWEEN ? AND ?', $r)],
                ['Libros perdidos en préstamo', (int)valor("SELECT COUNT(*) FROM prestamos WHERE estado IN ('perdido','repuesto') AND fecha_devolucion BETWEEN ? AND ?", $r)],
                ['Reposiciones recibidas', (int)valor('SELECT COUNT(*) FROM prestamos WHERE fecha_reposicion BETWEEN ? AND ?', $r)],
                ['Lectores atendidos (distintos)', (int)valor('SELECT COUNT(DISTINCT lector_id) FROM prestamos WHERE fecha_prestamo BETWEEN ? AND ?', $r)],
                ['Materiales nuevos registrados', (int)valor('SELECT COUNT(*) FROM materiales WHERE creado_en BETWEEN ? AND ?', $r)],
                ['Préstamos pendientes al cierre del mes', (int)valor("SELECT COUNT(*) FROM prestamos WHERE fecha_prestamo <= ? AND (fecha_devolucion IS NULL OR fecha_devolucion > ?)", [$r[1], $r[1]])],
            ];
            foreach (filas("SELECT COALESCE(NULLIF(m.area,''),'Sin área') AS area, COUNT(*) AS n FROM prestamos p JOIN materiales m ON m.id = p.material_id
                            WHERE p.fecha_prestamo BETWEEN ? AND ? GROUP BY area ORDER BY n DESC", $r) as $a) {
                $filas[] = ['Préstamos del área: ' . $a['area'], (int)$a['n']];
            }
            foreach (filas('SELECT m.titulo, COUNT(*) AS n FROM prestamos p JOIN materiales m ON m.id = p.material_id
                            WHERE p.fecha_prestamo BETWEEN ? AND ? GROUP BY m.id ORDER BY n DESC, m.titulo LIMIT 5', $r) as $i => $t) {
                $filas[] = ['Más prestado #' . ($i + 1) . ': ' . $t['titulo'], (int)$t['n']];
            }
            return ['titulo' => TIPOS_REPORTE[$tipo] . ' · ' . MESES[$mes] . ' ' . $anio, 'periodo' => 'Del ' . fecha_es($ini) . ' al ' . fecha_es($fin),
                'columnas' => ['Indicador', 'Cantidad'], 'filas' => $filas];

        case 'anual':
            $filas = [];
            $tot = [0, 0, 0, 0, 0];
            for ($mm = 1; $mm <= 12; $mm++) {
                $ini = sprintf('%04d-%02d-01', $anio, $mm);
                $r = [$ini . ' 00:00:00', date('Y-m-t', strtotime($ini)) . ' 23:59:59'];
                $v = [
                    (int)valor('SELECT COUNT(*) FROM prestamos WHERE fecha_prestamo BETWEEN ? AND ?', $r),
                    (int)valor("SELECT COUNT(*) FROM prestamos WHERE estado IN ('devuelto','repuesto') AND fecha_devolucion BETWEEN ? AND ?", $r),
                    (int)valor('SELECT COUNT(*) FROM renovaciones WHERE fecha BETWEEN ? AND ?', $r),
                    (int)valor("SELECT COUNT(*) FROM prestamos WHERE estado IN ('perdido','repuesto') AND fecha_devolucion BETWEEN ? AND ?", $r),
                    (int)valor('SELECT COUNT(DISTINCT lector_id) FROM prestamos WHERE fecha_prestamo BETWEEN ? AND ?', $r),
                ];
                foreach ($v as $k => $x) $tot[$k] += $x;
                $filas[] = array_merge([MESES[$mm]], $v);
            }
            $tot[4] = (int)valor('SELECT COUNT(DISTINCT lector_id) FROM prestamos WHERE YEAR(fecha_prestamo) = ?', [$anio]);
            $filas[] = array_merge(['TOTAL ' . $anio], $tot);
            return ['titulo' => TIPOS_REPORTE[$tipo] . ' ' . $anio, 'periodo' => 'Año ' . $anio,
                'columnas' => ['Mes', 'Préstamos', 'Devoluciones', 'Renovaciones', 'Pérdidas', 'Lectores atendidos'], 'filas' => $filas];
    }
    return ['titulo' => '', 'periodo' => '', 'columnas' => [], 'filas' => []];
}
