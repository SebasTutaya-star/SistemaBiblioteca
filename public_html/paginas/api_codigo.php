<?php
// RF-06: sugerir y validar códigos en línea desde el formulario.
$accion = g('accion');
if ($accion === 'sugerir') {
    json_respuesta(['codigo' => sugerir_codigo(g('area'), g('titulo'), g('anio'))]);
}
$codigo = normalizar_codigo(g('codigo'));
$error = validar_codigo($codigo);
$dup = $error ? null : codigo_existente($codigo, (int)g('id'));
json_respuesta([
    'codigo'   => $codigo,
    'valido'   => !$error && !$dup,
    'mensaje'  => $error ?: ($dup ? 'Ya existe: «' . $dup['titulo'] . '». Si son más copias, aumente la cantidad de ese registro.' : 'Código disponible.'),
]);
