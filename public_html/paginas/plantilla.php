<?php
// Plantillas Excel para la importación del catálogo (RF-21) y del padrón (RF-03).
require_once RAIZ . '/app/lib/excel.php';
$tipo = g('tipo');
if ($tipo === 'catalogo') {
    exigir('catalogo.importar');
    $cols = ['codigo', 'titulo', 'autor', 'editorial', 'anio', 'area', 'nivel', 'tema', 'descripcion', 'cantidad', 'ubicacion', 'tipo'];
    $filas = [['COM-RIO-1958', 'Los ríos profundos', 'José María Arguedas', 'Losada', 1958, 'Comunicación', 'Secundaria', 'Novela indigenista', 'Ejemplo: borre esta fila', 4, 'Estante C-01', 'Obra literaria']];
} elseif ($tipo === 'padron') {
    exigir('usuarios.gestionar');
    $cols = ['dni', 'nombres', 'apellidos', 'correo', 'tipo', 'grado', 'seccion'];
    $filas = [['74561230', 'Lucía', 'Pariona Tito', 'lucia.pariona@iesanjuan.edu.pe', 'estudiante', '4to', 'A'],
              ['70112233', 'Jorge', 'Gamboa Ruiz', 'jorge.gamboa@iesanjuan.edu.pe', 'docente', '', '']];
} else {
    redirigir('inicio');
}
if (!class_exists('ZipArchive')) {
    $csv = "\xEF\xBB\xBF" . implode(';', $cols) . "\n";
    foreach ($filas as $f) $csv .= implode(';', $f) . "\n";
    descargar($csv, "plantilla_$tipo.csv", 'text/csv; charset=utf-8');
}
// Sin título: la fila 1 es el encabezado que espera la importación
descargar(xlsx_generar('', $cols, $filas, 'Plantilla'), "plantilla_$tipo.xlsx", 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
