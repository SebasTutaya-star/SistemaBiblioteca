<?php
/**
 * Reglas del catálogo compartidas por el formulario y la importación (RF-04, RF-06, RF-07, RF-21).
 */

/**
 * Valida un código según el esquema de la biblioteca (RF-06).
 * Se acepta el esquema actual (no estandarizado) con caracteres seguros; si la Bibliotecaria
 * define un patrón en "Reglas de préstamo", también se exige ese patrón.
 */
function validar_codigo(string $codigo): ?string
{
    if ($codigo === '') return 'El código es obligatorio.';
    if (mb_strlen($codigo) > 40) return 'El código no debe superar 40 caracteres.';
    if (!preg_match('/^[A-Z0-9ÁÉÍÓÚÑ][A-Z0-9ÁÉÍÓÚÑ .\-\/_]*$/u', $codigo)) {
        return 'El código solo puede contener letras, números, espacios, guiones (-), puntos, barras (/) o guion bajo.';
    }
    $patron = trim((string)conf('patron_codigo', ''));
    if ($patron !== '' && @preg_match('/' . str_replace('/', '\/', $patron) . '/u', $codigo) !== 1) {
        return 'El código no sigue el esquema definido por la biblioteca (' . $patron . ').';
    }
    return null;
}

function codigo_existente(string $codigo, int $excluirId = 0): ?array
{
    return fila('SELECT id, titulo FROM materiales WHERE codigo = ? AND id <> ?', [$codigo, $excluirId]);
}

/** Abreviatura de 3 letras sin tildes ni palabras vacías. */
function abreviar(string $texto): string
{
    $vacias = ['EL', 'LA', 'LOS', 'LAS', 'DE', 'DEL', 'Y', 'EN', 'UN', 'UNA', 'A', 'AL', 'POR', 'PARA'];
    $pal = preg_split('/[^A-Z0-9]+/', strtoupper(sin_tildes($texto)), -1, PREG_SPLIT_NO_EMPTY);
    foreach ($pal as $w) {
        if (!in_array($w, $vacias, true)) return substr($w, 0, 3);
    }
    return 'GEN';
}

/** Propone un código único con el esquema ÁREA-TÍTULO-AÑO (ej. COM-CUE-2023). */
function sugerir_codigo(string $area, string $titulo, $anio): string
{
    $base = abreviar($area ?: 'GEN') . '-' . abreviar($titulo) . ($anio ? '-' . (int)$anio : '');
    $codigo = $base;
    $n = 2;
    while (codigo_existente($codigo)) {
        $codigo = $base . '-' . $n++;
    }
    return $codigo;
}

/** Busca un registro bibliográfico equivalente (mismo título, autor, editorial y año). */
function material_equivalente(string $titulo, ?string $autor, ?string $editorial, $anio): ?array
{
    return fila("SELECT id, codigo, titulo FROM materiales
                 WHERE LOWER(titulo) = LOWER(?) AND LOWER(COALESCE(autor,'')) = LOWER(?)
                   AND LOWER(COALESCE(editorial,'')) = LOWER(?) AND COALESCE(anio,0) = ?",
                [$titulo, (string)$autor, (string)$editorial, (int)$anio]);
}

function tipos_material_activos(): array
{
    return filas('SELECT * FROM tipos_material WHERE activo = 1 ORDER BY nombre');
}
