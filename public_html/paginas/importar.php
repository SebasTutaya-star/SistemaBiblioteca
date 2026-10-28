<?php
// RF-21: importación masiva del catálogo desde Excel, validando la estructura y evitando duplicados.
require_once RAIZ . '/app/lib/excel.php';

const COLUMNAS_CATALOGO = ['codigo', 'titulo', 'autor', 'editorial', 'anio', 'area', 'nivel', 'tema', 'descripcion', 'cantidad', 'ubicacion', 'tipo'];
const OBLIGATORIAS_CATALOGO = ['titulo', 'cantidad'];

$resumen = null;

if (es_post() && p('accion') === 'confirmar') {
    csrf_verificar();
    $lote = $_SESSION['import_catalogo'] ?? null;
    if (!$lote || !hash_equals($lote['token'], p('token'))) {
        flash('error', 'La vista previa expiró. Vuelva a cargar el archivo.');
        redirigir('importar');
    }
    $pdo = db();
    $pdo->beginTransaction();
    $insertados = 0;
    $omitidos = [];
    foreach ($lote['filas'] as $f) {
        // Revalidación al guardar por si alguien registró el mismo código mientras tanto
        if (codigo_existente($f['codigo'])) { $omitidos[] = "Fila {$f['_fila']}: el código {$f['codigo']} ya existe."; continue; }
        q('INSERT INTO materiales (codigo, titulo, autor, editorial, anio, area, nivel, tema, descripcion, tipo_material_id, cantidad_total, ubicacion, creado_por)
           VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
          [$f['codigo'], $f['titulo'], $f['autor'] ?: null, $f['editorial'] ?: null, $f['anio'] !== '' ? (int)$f['anio'] : null,
           $f['area'] ?: null, $f['nivel'] ?: null, $f['tema'] ?: null, $f['descripcion'] ?: null, $f['tipo_id'],
           (int)$f['cantidad'], $f['ubicacion'] ?: null, usuario()['id']]);
        registrar_movimiento((int)$pdo->lastInsertId(), 'importacion', (int)$f['cantidad'], 'Importado desde ' . $lote['archivo'] . ' (fila ' . $f['_fila'] . ')');
        $insertados++;
    }
    $pdo->commit();
    unset($_SESSION['import_catalogo']);
    flash('ok', "Importación terminada: $insertados material(es) registrados." . ($omitidos ? ' Omitidos: ' . implode(' ', $omitidos) : ''));
    redirigir('catalogo');
}

if (es_post() && p('accion') === 'validar') {
    csrf_verificar();
    $arch = $_FILES['archivo'] ?? null;
    try {
        if (!$arch || $arch['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Seleccione un archivo para importar.');
        if ($arch['size'] > 5 * 1024 * 1024) throw new RuntimeException('El archivo supera 5 MB.');
        $hoja = leer_hoja($arch['tmp_name'], $arch['name']);
        if (count($hoja) < 2) throw new RuntimeException('El archivo no tiene datos. La primera fila debe tener los encabezados y las siguientes, los libros.');
        if (count($hoja) > 5001) throw new RuntimeException('Máximo 5000 filas por archivo. Divida el archivo en partes.');

        // Estructura: encabezados
        $nroEnc = array_key_first($hoja);
        $enc = array_map('normalizar_encabezado', $hoja[$nroEnc]);
        unset($hoja[$nroEnc]); // se conservan los números de fila reales del Excel
        $faltan = array_diff(OBLIGATORIAS_CATALOGO, $enc);
        if ($faltan) throw new RuntimeException('Faltan columnas obligatorias: ' . implode(', ', $faltan) . '. Descargue la plantilla para ver el formato.');
        $ignoradas = array_values(array_filter(array_diff($enc, COLUMNAS_CATALOGO), fn($c) => $c !== ''));
        $tipos = [];
        foreach (filas('SELECT id, nombre FROM tipos_material') as $t) $tipos[mb_strtolower(sin_tildes($t['nombre']))] = (int)$t['id'];

        $validas = [];
        $errores = [];
        $avisos = [];
        $codigosArchivo = [];
        $clavesArchivo = [];
        foreach ($hoja as $nro => $valores) {
            if (!array_filter($valores, fn($v) => $v !== '')) continue; // fila vacía
            $f = ['_fila' => $nro];
            foreach (COLUMNAS_CATALOGO as $c) {
                $i = array_search($c, $enc, true);
                $f[$c] = $i === false ? '' : trim((string)($valores[$i] ?? ''));
            }
            $err = [];
            if ($f['titulo'] === '') $err[] = 'falta el título';
            if (mb_strlen($f['titulo']) > 255) $err[] = 'título demasiado largo';
            if (!ctype_digit($f['cantidad']) || (int)$f['cantidad'] < 1 || (int)$f['cantidad'] > 9999) $err[] = 'cantidad inválida («' . $f['cantidad'] . '»): debe ser un número entero de 1 a 9999';
            if ($f['anio'] !== '' && (!ctype_digit($f['anio']) || $f['anio'] < 1500 || $f['anio'] > (int)date('Y') + 1)) $err[] = 'año inválido («' . $f['anio'] . '»)';
            foreach (['autor' => 200, 'editorial' => 150, 'area' => 80, 'nivel' => 40, 'tema' => 150, 'ubicacion' => 80] as $c => $max) {
                if (mb_strlen($f[$c]) > $max) $err[] = "$c supera $max caracteres";
            }
            $f['tipo_id'] = null;
            if ($f['tipo'] !== '') {
                $f['tipo_id'] = $tipos[mb_strtolower(sin_tildes($f['tipo']))] ?? null;
                if (!$f['tipo_id']) $avisos[] = "Fila $nro: tipo de material «{$f['tipo']}» no reconocido; se importará sin tipo.";
            }
            // Código: se respeta el existente; si viene vacío se genera
            if ($f['codigo'] === '' && !$err) {
                $f['codigo'] = sugerir_codigo($f['area'], $f['titulo'], $f['anio']);
                while (isset($codigosArchivo[$f['codigo']])) $f['codigo'] .= 'X';
                $avisos[] = "Fila $nro: sin código; se asignará {$f['codigo']}.";
            } else {
                $f['codigo'] = normalizar_codigo($f['codigo']);
                if ($ec = validar_codigo($f['codigo'])) $err[] = $ec;
            }
            // Duplicados
            if (!$err) {
                if ($dup = codigo_existente($f['codigo'])) $err[] = "el código {$f['codigo']} ya está registrado («{$dup['titulo']}»). Si son más ejemplares, aumente la cantidad en el catálogo";
                elseif (isset($codigosArchivo[$f['codigo']])) $err[] = "código {$f['codigo']} repetido en el archivo (fila {$codigosArchivo[$f['codigo']]}). Si es el mismo libro, sume las cantidades en una sola fila";
                $clave = mb_strtolower($f['titulo'] . '|' . $f['autor'] . '|' . $f['editorial'] . '|' . (int)$f['anio']);
                if (!$err && ($eq = material_equivalente($f['titulo'], $f['autor'], $f['editorial'], $f['anio']))) $err[] = "el libro ya existe en el catálogo con el código {$eq['codigo']}";
                elseif (!$err && isset($clavesArchivo[$clave])) $err[] = "libro repetido en el archivo (fila {$clavesArchivo[$clave]})";
            }
            if ($err) {
                $errores[] = "Fila $nro: " . implode('; ', $err) . '.';
            } else {
                $codigosArchivo[$f['codigo']] = $nro;
                $clavesArchivo[$clave] = $nro;
                $validas[] = $f;
            }
        }
        $token = bin2hex(random_bytes(16));
        $_SESSION['import_catalogo'] = ['token' => $token, 'filas' => $validas, 'archivo' => basename($arch['name'])];
        $resumen = compact('validas', 'errores', 'avisos', 'ignoradas', 'token') + ['archivo' => $arch['name']];
    } catch (RuntimeException $ex) {
        flash('error', $ex->getMessage());
        redirigir('importar');
    }
}

pagina_inicio('Importar catálogo', 'importar');
encabezado('Importar catálogo desde Excel', 'Carga masiva de libros. El registro manual sigue disponible para libros nuevos y correcciones.');

if (!$resumen): ?>
<div class="pasos" aria-label="Proceso de importación"><span class="paso activo">1. Cargar archivo</span><span class="paso">2. Revisar datos</span><span class="paso">3. Confirmar importación</span></div>
<div class="rejilla-2">
  <form method="post" enctype="multipart/form-data" class="tarjeta" action="<?= e(url('importar')) ?>">
    <?= csrf_campo() ?><input type="hidden" name="accion" value="validar">
    <h2>1. Seleccione el archivo</h2>
    <label class="zona-archivo"><?= icono('upload') ?><strong>Selecciona tu archivo Excel o CSV</strong><small>Máximo 5 MB · Revisarás los datos antes de confirmar.</small><input type="file" name="archivo" accept=".xlsx,.csv" required></label>
    <button class="btn btn-primario">Validar archivo</button>
    <p class="nota">Primero verá una vista previa con los errores encontrados. Nada se guarda hasta que confirme.</p>
  </form>
  <section class="tarjeta">
    <h2>Formato esperado</h2>
    <p>La primera fila debe contener los encabezados. Obligatorios: <strong>titulo</strong> y <strong>cantidad</strong>.</p>
    <p>Opcionales: codigo, autor, editorial, anio, area, nivel, tema, descripcion, ubicacion, tipo
      (<?= e(implode(', ', array_column(tipos_material_activos(), 'nombre'))) ?>).</p>
    <ul class="lista-simple">
      <li>Si un libro tiene varias copias, use <strong>una sola fila</strong> con la cantidad total.</li>
      <li>Si el código está vacío, el sistema propone uno con el formato ÁREA-TÍTULO-AÑO.</li>
      <li>Se rechazan códigos ya registrados y libros repetidos.</li>
    </ul>
    <a class="btn" href="<?= e(url('plantilla', ['tipo' => 'catalogo'])) ?>">⬇ Descargar plantilla</a>
  </section>
</div>
<?php else: ?>
<section class="tarjeta">
  <h2>2. Revise la vista previa · <?= e($resumen['archivo']) ?></h2>
  <div class="cantidades">
    <div class="c-verde"><span><?= count($resumen['validas']) ?></span>Filas válidas</div>
    <div class="c-rojo"><span><?= count($resumen['errores']) ?></span>Filas con errores</div>
    <div class="c-ambar"><span><?= count($resumen['avisos']) ?></span>Avisos</div>
  </div>
  <?php if ($resumen['ignoradas']): ?><div class="aviso aviso-info">Columnas no reconocidas (se ignorarán): <?= e(implode(', ', $resumen['ignoradas'])) ?></div><?php endif; ?>
  <?php if ($resumen['errores']): ?>
    <h3>Filas que no se importarán</h3>
    <ul class="lista-errores"><?php foreach (array_slice($resumen['errores'], 0, 200) as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul>
    <?php if (count($resumen['errores']) > 200): ?><p class="nota">… y <?= count($resumen['errores']) - 200 ?> más.</p><?php endif; ?>
  <?php endif; ?>
  <?php if ($resumen['avisos']): ?>
    <h3>Avisos</h3>
    <ul class="lista-simple"><?php foreach (array_slice($resumen['avisos'], 0, 100) as $a): ?><li><?= e($a) ?></li><?php endforeach; ?></ul>
  <?php endif; ?>
  <?php if ($resumen['validas']): ?>
    <h3>Primeras filas a importar</h3>
    <div class="tabla-envoltura"><table class="tabla">
      <thead><tr><th>Fila</th><th>Código</th><th>Título</th><th>Autor</th><th>Área</th><th>Nivel</th><th class="num">Cant.</th><th>Ubicación</th></tr></thead>
      <tbody><?php foreach (array_slice($resumen['validas'], 0, 15) as $f): ?>
        <tr><td><?= $f['_fila'] ?></td><td class="mono"><?= e($f['codigo']) ?></td><td><?= e($f['titulo']) ?></td><td><?= e($f['autor']) ?></td>
            <td><?= e($f['area']) ?></td><td><?= e($f['nivel']) ?></td><td class="num"><?= (int)$f['cantidad'] ?></td><td><?= e($f['ubicacion']) ?></td></tr>
      <?php endforeach; ?></tbody>
    </table></div>
  <?php endif; ?>
  <form method="post" class="botonera izquierda" action="<?= e(url('importar')) ?>">
    <?= csrf_campo() ?><input type="hidden" name="accion" value="confirmar"><input type="hidden" name="token" value="<?= e($resumen['token']) ?>">
    <a class="btn" href="<?= e(url('importar')) ?>">Cancelar y cargar otro archivo</a>
    <?php if ($resumen['validas']): ?><button class="btn btn-primario">Importar <?= count($resumen['validas']) ?> material(es)</button><?php endif; ?>
  </form>
</section>
<?php endif;
pagina_fin();
