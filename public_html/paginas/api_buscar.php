<?php
// Búsqueda en línea de lectores y materiales para el formulario de préstamo.
$tipo = g('tipo');
$texto = g('q');
if (mb_strlen($texto) < 2) json_respuesta([]);
$like = '%' . $texto . '%';

if ($tipo === 'lector') {
    $rs = filas("SELECT id, dni, nombres, apellidos, tipo_lector, grado, seccion FROM usuarios
                 WHERE rol = 'lector' AND activo = 1
                   AND (dni LIKE ? OR correo LIKE ? OR CONCAT(nombres, ' ', apellidos) LIKE ? OR CONCAT(apellidos, ' ', nombres) LIKE ?)
                 ORDER BY apellidos LIMIT 10", [$like, $like, $like, $like]);
    $out = [];
    foreach ($rs as $r) {
        $s = situacion_lector((int)$r['id']);
        $max = limite_lector($r);
        $info = ucfirst((string)$r['tipo_lector']) . ($r['grado'] ? ' · ' . $r['grado'] . ' ' . $r['seccion'] : '')
              . ' · Activos: ' . $s['activos'] . ($max ? " de $max" : '')
              . ($s['vencidos'] ? ' · ⚠ ' . $s['vencidos'] . ' vencido(s)' : '')
              . ($s['perdidos'] ? ' · ⚠ ' . $s['perdidos'] . ' perdido(s)' : '');
        $out[] = ['id' => $r['id'], 'texto' => nombre_completo($r) . ' · DNI ' . $r['dni'], 'info' => $info];
    }
    json_respuesta($out);
}

if ($tipo === 'material') {
    $rs = filas('SELECT * FROM (' . sql_materiales() . ' WHERE m.activo = 1 AND (m.codigo LIKE ? OR m.titulo LIKE ? OR m.autor LIKE ?)) x
                 ORDER BY x.titulo LIMIT 10', [$like, $like, $like]);
    $out = [];
    foreach ($rs as $r) {
        $out[] = [
            'id'    => $r['id'],
            'texto' => $r['codigo'] . ' · ' . $r['titulo'],
            'info'  => $r['disponibles'] . ' disponible(s) · ' . ($r['tipo_nombre'] ?: 'Tipo no especificado') . ' · Ubicación: ' . ($r['ubicacion'] ?: '—'),
            'vence' => vencimiento_sugerido($r),
            'disponibles' => (int)$r['disponibles'],
        ];
    }
    json_respuesta($out);
}
json_respuesta([]);
