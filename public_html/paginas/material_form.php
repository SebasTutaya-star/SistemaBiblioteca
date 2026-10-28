<?php
// RF-04 (registro y modificación), RF-06 (códigos), RF-07 (cantidad de ejemplares con el mismo código).
$id = (int)(g('id') ?: p('id'));
$m = $id ? material_por_id($id) : null;
if ($id && !$m) { flash('error', 'Material no encontrado.'); redirigir('catalogo'); }

$campos = ['codigo','titulo','autor','editorial','anio','area','nivel','tema','descripcion','tipo_material_id','cantidad_total','ubicacion','activo'];
$d = $m ? array_intersect_key($m, array_flip($campos)) : array_fill_keys($campos, '') + ['cantidad_total' => 1, 'activo' => 1];
$errores = [];
$duplicado = null;

if (es_post()) {
    csrf_verificar();
    foreach ($campos as $c) $d[$c] = p($c);
    $d['codigo'] = normalizar_codigo($d['codigo']);
    $d['activo'] = $m ? (p('activo') === '0' ? 0 : 1) : 1;

    if ($err = validar_codigo($d['codigo'])) $errores['codigo'] = $err;
    elseif ($dup = codigo_existente($d['codigo'], $id)) {
        $errores['codigo'] = 'Este código ya pertenece a otro material.';
        $duplicado = $dup;
    }
    if ($d['titulo'] === '') $errores['titulo'] = 'El título es obligatorio.';
    if ($d['anio'] !== '' && (!ctype_digit($d['anio']) || $d['anio'] < 1500 || $d['anio'] > (int)date('Y') + 1)) {
        $errores['anio'] = 'Ingrese un año válido de 4 dígitos.';
    }
    if (!ctype_digit((string)$d['cantidad_total']) || (int)$d['cantidad_total'] < 1) {
        $errores['cantidad_total'] = 'La cantidad debe ser un número entero mayor o igual a 1.';
    } elseif ($m) {
        $minimo = (int)$m['cantidad_deteriorada'] + (int)$m['cantidad_perdida'] + (int)$m['prestados'];
        if ((int)$d['cantidad_total'] < $minimo) {
            $errores['cantidad_total'] = "No puede ser menor que $minimo (ejemplares prestados, deteriorados y perdidos). Para dar de baja copias use «Estados y cantidades».";
        }
    }
    $tipo = $d['tipo_material_id'] !== '' ? (int)$d['tipo_material_id'] : null;
    if ($tipo && !valor('SELECT id FROM tipos_material WHERE id = ?', [$tipo])) $errores['tipo_material_id'] = 'Tipo no válido.';
    if (mb_strlen($d['titulo']) > 255) $errores['titulo'] = 'El título es demasiado largo.';

    if (!$errores) {
        $vals = [$d['codigo'], $d['titulo'], $d['autor'] ?: null, $d['editorial'] ?: null, $d['anio'] !== '' ? (int)$d['anio'] : null,
                 $d['area'] ?: null, $d['nivel'] ?: null, $d['tema'] ?: null, $d['descripcion'] ?: null, $tipo,
                 (int)$d['cantidad_total'], $d['ubicacion'] ?: null];
        $pdo = db();
        $pdo->beginTransaction();
        if ($m) {
            q('UPDATE materiales SET codigo=?, titulo=?, autor=?, editorial=?, anio=?, area=?, nivel=?, tema=?, descripcion=?,
               tipo_material_id=?, cantidad_total=?, ubicacion=?, activo=? WHERE id=?', array_merge($vals, [$d['activo'], $id]));
            $dif = (int)$d['cantidad_total'] - (int)$m['cantidad_total'];
            if ($dif !== 0) registrar_movimiento($id, 'ajuste_cantidad', $dif, 'Cantidad total: ' . $m['cantidad_total'] . ' → ' . $d['cantidad_total']);
            if ((int)$m['activo'] !== $d['activo']) registrar_movimiento($id, $d['activo'] ? 'activar' : 'inactivar', 0, 'Desde el formulario de edición');
            if ($dif === 0 && (int)$m['activo'] === $d['activo']) registrar_movimiento($id, 'edicion', 0, 'Datos bibliográficos actualizados');
        } else {
            q('INSERT INTO materiales (codigo, titulo, autor, editorial, anio, area, nivel, tema, descripcion, tipo_material_id,
               cantidad_total, ubicacion, creado_por) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)', array_merge($vals, [usuario()['id']]));
            $id = (int)$pdo->lastInsertId();
            registrar_movimiento($id, 'alta', (int)$d['cantidad_total'], 'Registro manual');
        }
        $pdo->commit();
        flash('ok', $m ? 'Material actualizado.' : 'Material registrado correctamente.');
        redirigir('material_ver', ['id' => $id]);
    }
}

$err = fn($c) => isset($errores[$c]) ? '<span class="error-campo">' . e($errores[$c]) . '</span>' : '';
pagina_inicio($m ? 'Editar material' : 'Registrar material', 'catalogo');
encabezado($m ? 'Editar material' : 'Registrar material', $m ? $m['titulo'] : 'Complete los datos del nuevo material bibliográfico.');
?>
<?php if ($errores): ?><div class="aviso aviso-error">Revise los campos marcados.</div><?php endif; ?>
<?php if ($duplicado): ?>
  <div class="aviso aviso-info">El código ya está registrado para «<?= e($duplicado['titulo']) ?>».
    Si se trata de más ejemplares del mismo libro, no cree un registro nuevo:
    <a href="<?= e(url('material_form', ['id' => $duplicado['id']])) ?>">aumente la cantidad en ese material</a>.</div>
<?php endif; ?>
<form method="post" class="tarjeta formulario" action="<?= e(url('material_form')) ?>" novalidate>
  <?= csrf_campo() ?>
  <input type="hidden" name="id" value="<?= (int)$id ?>">
  <div class="rejilla-form">
    <label class="col-2">Título *<input name="titulo" id="f-titulo" value="<?= e($d['titulo']) ?>" required maxlength="255"><?= $err('titulo') ?></label>
    <label>Autor<input name="autor" value="<?= e($d['autor']) ?>" maxlength="200"></label>
    <label>Editorial<input name="editorial" value="<?= e($d['editorial']) ?>" maxlength="150"></label>
    <label>Año<input name="anio" id="f-anio" value="<?= e($d['anio']) ?>" inputmode="numeric" maxlength="4"><?= $err('anio') ?></label>
    <label>Área
      <input name="area" id="f-area" value="<?= e($d['area']) ?>" list="lista-areas" maxlength="80">
      <datalist id="lista-areas"><?php foreach (valores_distintos('area') as $a) echo '<option value="' . e($a) . '">'; ?></datalist>
    </label>
    <label>Nivel / grado
      <input name="nivel" value="<?= e($d['nivel']) ?>" list="lista-niveles" maxlength="40">
      <datalist id="lista-niveles"><?php foreach (array_unique(array_merge(['Primaria','Secundaria','1ro','2do','3ro','4to','5to'], valores_distintos('nivel'))) as $n) echo '<option value="' . e($n) . '">'; ?></datalist>
    </label>
    <label>Tema<input name="tema" value="<?= e($d['tema']) ?>" maxlength="150"></label>
    <label>Tipo de material (define el plazo de préstamo)
      <select name="tipo_material_id"><option value="">— Sin especificar —</option>
        <?php foreach (tipos_material_activos() as $t): ?>
          <option value="<?= (int)$t['id'] ?>" <?= (string)$t['id'] === (string)$d['tipo_material_id'] ? 'selected' : '' ?>><?= e($t['nombre']) ?></option>
        <?php endforeach; ?>
      </select><?= $err('tipo_material_id') ?>
    </label>
    <label class="col-2">Descripción<textarea name="descripcion" rows="3" maxlength="2000"><?= e($d['descripcion']) ?></textarea></label>
  </div>
  <fieldset>
    <legend>Código e inventario</legend>
    <div class="rejilla-form">
      <label>Código *
        <span class="con-boton">
          <input name="codigo" id="f-codigo" value="<?= e($d['codigo']) ?>" required maxlength="40" class="mono" data-id="<?= (int)$id ?>">
          <button type="button" class="btn" id="btn-sugerir">Sugerir</button>
        </span>
        <small id="codigo-estado" class="ayuda">Use el código que ya tiene el libro. Si no tiene, pulse «Sugerir».</small>
        <?= $err('codigo') ?>
      </label>
      <label>Cantidad de ejemplares *<input name="cantidad_total" type="number" min="1" value="<?= e($d['cantidad_total']) ?>" required><?= $err('cantidad_total') ?>
        <small class="ayuda">Todas las copias del mismo libro comparten este código.</small></label>
      <label>Ubicación física<input name="ubicacion" value="<?= e($d['ubicacion']) ?>" maxlength="80" placeholder="Ej.: Estante C-02"></label>
      <?php if ($m): ?>
      <label>Estado del registro
        <select name="activo"><option value="1" <?= $d['activo'] ? 'selected' : '' ?>>Activo</option><option value="0" <?= !$d['activo'] ? 'selected' : '' ?>>Inactivo (no se presta; conserva historial)</option></select>
      </label>
      <?php endif; ?>
    </div>
    <?php if ($m): ?><p class="nota">Para registrar ejemplares deteriorados, perdidos o recuperados use <a href="<?= e(url('material_estado', ['id' => $id])) ?>">Estados y cantidades</a>.</p><?php endif; ?>
  </fieldset>
  <div class="botonera">
    <a class="btn" href="<?= e($m ? url('material_ver', ['id' => $id]) : url('catalogo')) ?>">Cancelar</a>
    <button class="btn btn-primario">Guardar</button>
  </div>
</form>
<?php pagina_fin();
