<?php
// RF-03: actualización del padrón de estudiantes y docentes al inicio de cada periodo académico (Excel).
// Los ingresos sueltos durante el año se registran a mano en "Nuevo usuario".
require_once RAIZ . '/app/lib/excel.php';
const COLUMNAS_PADRON = ['dni', 'nombres', 'apellidos', 'correo', 'tipo', 'grado', 'seccion'];

$resumen = null;
$periodo = p('periodo') ?: (string)conf('periodo_actual', date('Y'));

if (es_post() && p('accion') === 'confirmar') {
    csrf_verificar();
    $lote = $_SESSION['import_padron'] ?? null;
    if (!$lote || !hash_equals($lote['token'], p('token'))) { flash('error', 'La vista previa expiró. Cargue el archivo de nuevo.'); redirigir('padron'); }
    $pdo = db();
    $pdo->beginTransaction();
    $nuevos = $actualizados = 0;
    foreach ($lote['filas'] as $f) {
        $ex = fila('SELECT id FROM usuarios WHERE dni = ?', [$f['dni']]);
        if ($ex) {
            q("UPDATE usuarios SET nombres=?, apellidos=?, correo=?, tipo_lector=?, grado=?, seccion=?, periodo=?, activo=1 WHERE id=? AND rol='lector'",
              [$f['nombres'], $f['apellidos'], $f['correo'], $f['tipo'], $f['grado'] ?: null, $f['seccion'] ?: null, $lote['periodo'], $ex['id']]);
            $actualizados++;
        } else {
            q("INSERT INTO usuarios (dni, nombres, apellidos, correo, rol, tipo_lector, grado, seccion, periodo, clave_hash, debe_cambiar_clave)
               VALUES (?,?,?,?, 'lector', ?,?,?,?,?,1)",
              [$f['dni'], $f['nombres'], $f['apellidos'], $f['correo'], $f['tipo'], $f['grado'] ?: null, $f['seccion'] ?: null, $lote['periodo'], password_hash($f['dni'], PASSWORD_DEFAULT)]);
            $nuevos++;
        }
    }
    $desactivados = 0;
    if ($lote['desactivar']) {
        $dnis = array_column($lote['filas'], 'dni');
        $marcas = implode(',', array_fill(0, count($dnis), '?'));
        $desactivados = q("UPDATE usuarios SET activo = 0 WHERE rol = 'lector' AND activo = 1 AND dni NOT IN ($marcas)", $dnis)->rowCount();
    }
    q("UPDATE configuracion SET valor = ? WHERE clave = 'periodo_actual'", [$lote['periodo']]);
    $pdo->commit();
    unset($_SESSION['import_padron']);
    flash('ok', "Padrón $lote[periodo] actualizado: $nuevos nuevo(s), $actualizados actualizado(s)" . ($lote['desactivar'] ? ", $desactivados desactivado(s) por no figurar en el padrón" : '') . '.');
    redirigir('usuarios', ['rol' => 'lector']);
}

if (es_post() && p('accion') === 'validar') {
    csrf_verificar();
    $arch = $_FILES['archivo'] ?? null;
    try {
        if (!preg_match('/^\d{4}(-[I]{1,2})?$/', $periodo)) throw new RuntimeException('Periodo no válido (ej.: 2027).');
        if (!$arch || $arch['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Seleccione un archivo.');
        if ($arch['size'] > 5 * 1024 * 1024) throw new RuntimeException('El archivo supera 5 MB.');
        $hoja = leer_hoja($arch['tmp_name'], $arch['name']);
        $nroEnc = array_key_first($hoja);
        $enc = array_map('normalizar_encabezado', $hoja[$nroEnc] ?? []);
        unset($hoja[$nroEnc]);
        $faltan = array_diff(['dni', 'nombres', 'apellidos', 'correo', 'tipo'], $enc);
        if ($faltan) throw new RuntimeException('Faltan columnas obligatorias: ' . implode(', ', $faltan) . '. Descargue la plantilla.');
        if (!$hoja) throw new RuntimeException('El archivo no tiene filas de datos.');

        $validas = $errores = [];
        $dnis = $correos = [];
        foreach ($hoja as $nro => $vals) {
            if (!array_filter($vals, fn($v) => $v !== '')) continue;
            $f = [];
            foreach (COLUMNAS_PADRON as $c) { $i = array_search($c, $enc, true); $f[$c] = $i === false ? '' : trim((string)($vals[$i] ?? '')); }
            if (ctype_digit($f['dni']) && strlen($f['dni']) === 7) $f['dni'] = '0' . $f['dni']; // Excel suele quitar el cero inicial del DNI
            $f['correo'] = mb_strtolower($f['correo']);
            $f['tipo'] = mb_strtolower(sin_tildes($f['tipo']));
            $err = [];
            if (!preg_match('/^\d{8,12}$/', $f['dni'])) $err[] = 'DNI inválido';
            elseif (isset($dnis[$f['dni']])) $err[] = 'DNI repetido (fila ' . $dnis[$f['dni']] . ')';
            if ($f['nombres'] === '' || $f['apellidos'] === '') $err[] = 'faltan nombres o apellidos';
            if (!filter_var($f['correo'], FILTER_VALIDATE_EMAIL)) $err[] = 'correo inválido';
            elseif (isset($correos[$f['correo']])) $err[] = 'correo repetido (fila ' . $correos[$f['correo']] . ')';
            elseif ($otro = fila('SELECT dni FROM usuarios WHERE correo = ? AND dni <> ?', [$f['correo'], $f['dni']])) $err[] = 'el correo ya pertenece a otro usuario (DNI ' . $otro['dni'] . ')';
            if (!in_array($f['tipo'], ['estudiante', 'docente'], true)) $err[] = 'tipo debe ser «estudiante» o «docente»';
            if (!$err && ($ex = fila('SELECT rol FROM usuarios WHERE dni = ?', [$f['dni']])) && $ex['rol'] !== 'lector') $err[] = 'el DNI corresponde a una cuenta de personal; edítela manualmente';
            if ($f['tipo'] === 'docente') $f['grado'] = $f['seccion'] = '';
            if ($err) { $errores[] = "Fila $nro: " . implode('; ', $err) . '.'; continue; }
            $dnis[$f['dni']] = $nro;
            $correos[$f['correo']] = $nro;
            $f['_existe'] = (bool)valor('SELECT id FROM usuarios WHERE dni = ?', [$f['dni']]);
            $validas[] = $f;
        }
        $desactivar = p('desactivar') === '1';
        $porDesactivar = 0;
        if ($desactivar && $validas) {
            $lista = array_column($validas, 'dni');
            $porDesactivar = (int)valor("SELECT COUNT(*) FROM usuarios WHERE rol='lector' AND activo=1 AND dni NOT IN (" . implode(',', array_fill(0, count($lista), '?')) . ')', $lista);
        }
        $token = bin2hex(random_bytes(16));
        $_SESSION['import_padron'] = ['token' => $token, 'filas' => $validas, 'periodo' => $periodo, 'desactivar' => $desactivar];
        $resumen = compact('validas', 'errores', 'token', 'porDesactivar', 'desactivar') + ['archivo' => $arch['name']];
    } catch (RuntimeException $ex) {
        flash('error', $ex->getMessage());
        redirigir('padron');
    }
}

pagina_inicio('Padrón anual', 'padron');
encabezado('Actualizar padrón de lectores', 'Carga de estudiantes y docentes al inicio del periodo académico.');
if (!$resumen): ?>
<div class="pasos" aria-label="Proceso de importación"><span class="paso activo">1. Cargar archivo</span><span class="paso">2. Revisar datos</span><span class="paso">3. Confirmar importación</span></div>
<div class="rejilla-2">
  <form method="post" enctype="multipart/form-data" class="tarjeta" action="<?= e(url('padron')) ?>">
    <?= csrf_campo() ?><input type="hidden" name="accion" value="validar">
    <label>Periodo académico<input name="periodo" value="<?= e($periodo) ?>" required maxlength="10"></label>
    <label class="zona-archivo"><?= icono('upload') ?><strong>Selecciona tu archivo Excel o CSV</strong><small>Máximo 5 MB · Revisarás los datos antes de confirmar.</small><input type="file" name="archivo" accept=".xlsx,.csv" required></label>
    <label class="check"><input type="checkbox" name="desactivar" value="1"> Desactivar a los lectores que no figuren en este padrón (egresados, retirados). Su historial se conserva.</label>
    <button class="btn btn-primario">Validar archivo</button>
  </form>
  <section class="tarjeta">
    <h2>Cómo funciona</h2>
    <ul class="lista-simple">
      <li>Columnas: <strong>dni, nombres, apellidos, correo, tipo</strong> (estudiante o docente), grado, seccion.</li>
      <li>Si el DNI ya existe, se actualizan sus datos (por ejemplo, el nuevo grado) y se activa.</li>
      <li>Si es nuevo, se crea su cuenta con la <strong>contraseña inicial = DNI</strong>.</li>
      <li>Los traslados o ingresos durante el año se registran en <a href="<?= e(url('usuario_form')) ?>">Nuevo usuario</a>.</li>
    </ul>
    <a class="btn" href="<?= e(url('plantilla', ['tipo' => 'padron'])) ?>">⬇ Descargar plantilla</a>
  </section>
</div>
<?php else:
  $nuevos = count(array_filter($resumen['validas'], fn($f) => !$f['_existe'])); ?>
<section class="tarjeta">
  <h2>Vista previa · <?= e($resumen['archivo']) ?> · Periodo <?= e($periodo) ?></h2>
  <div class="cantidades">
    <div class="c-verde"><span><?= $nuevos ?></span>Cuentas nuevas</div>
    <div class="c-azul"><span><?= count($resumen['validas']) - $nuevos ?></span>Se actualizarán</div>
    <div class="c-rojo"><span><?= count($resumen['errores']) ?></span>Filas con errores</div>
    <?php if ($resumen['desactivar']): ?><div class="c-ambar"><span><?= $resumen['porDesactivar'] ?></span>Se desactivarán</div><?php endif; ?>
  </div>
  <?php if ($resumen['errores']): ?><h3>Filas que no se procesarán</h3><ul class="lista-errores"><?php foreach (array_slice($resumen['errores'], 0, 200) as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul><?php endif; ?>
  <form method="post" class="botonera izquierda" action="<?= e(url('padron')) ?>">
    <?= csrf_campo() ?><input type="hidden" name="accion" value="confirmar"><input type="hidden" name="token" value="<?= e($resumen['token']) ?>">
    <a class="btn" href="<?= e(url('padron')) ?>">Cancelar</a>
    <?php if ($resumen['validas']): ?><button class="btn btn-primario">Aplicar padrón (<?= count($resumen['validas']) ?> lectores)</button><?php endif; ?>
  </form>
</section>
<?php endif;
pagina_fin();
