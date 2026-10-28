<?php
// RF-09: búsqueda del catálogo (Lector). RF-10: información administrativa y ubicación (Bibliotecaria/Auxiliar).
$gestion = puede('catalogo.gestionar');

$texto  = g('q');
$campo  = g('campo', 'todos');
$area   = g('area');
$nivel  = g('nivel');
$soloDisp = g('disp') === '1';
$inactivos = $gestion && g('inactivos') === '1';

$campos = ['todos' => 'Todos los campos', 'titulo' => 'Título', 'autor' => 'Autor', 'tema' => 'Tema',
           'area' => 'Área', 'nivel' => 'Nivel / grado', 'codigo' => 'Código'];
if (!isset($campos[$campo])) $campo = 'todos';

$where = [];
$params = [];
if (!$inactivos) $where[] = 'm.activo = 1';
if ($texto !== '') {
    $like = '%' . $texto . '%';
    if ($campo === 'todos') {
        $where[] = '(m.titulo LIKE ? OR m.autor LIKE ? OR m.tema LIKE ? OR m.area LIKE ? OR m.nivel LIKE ? OR m.codigo LIKE ? OR m.editorial LIKE ?)';
        array_push($params, $like, $like, $like, $like, $like, $like, $like);
    } else {
        $where[] = "m.$campo LIKE ?";   // $campo validado contra la lista blanca
        $params[] = $like;
    }
}
if ($area !== '')  { $where[] = 'm.area = ?';  $params[] = $area; }
if ($nivel !== '') { $where[] = 'm.nivel = ?'; $params[] = $nivel; }

$base = 'SELECT * FROM (' . sql_materiales() . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ') x';
if ($soloDisp) $base .= ' WHERE x.disponibles > 0';

$total = (int)valor("SELECT COUNT(*) FROM ($base) c", $params);
[$offset, $limite, $pag] = paginacion(25);
$materiales = filas("$base ORDER BY x.titulo LIMIT $limite OFFSET $offset", $params);

$filtros = array_filter(['q' => $texto, 'campo' => $campo, 'area' => $area, 'nivel' => $nivel,
                         'disp' => $soloDisp ? '1' : '', 'inactivos' => $inactivos ? '1' : '']);

pagina_inicio('Catálogo', 'catalogo');
encabezado('Catálogo bibliográfico', $total . ' material(es) encontrados',
    $gestion ? '<a class="btn btn-primario" href="' . e(url('material_form')) . '">+ Registrar material</a>' : '');
?>
<form method="get" class="buscador tarjeta" action="index.php">
  <input type="hidden" name="p" value="catalogo">
  <div class="fila-busqueda">
    <input type="search" name="q" value="<?= e($texto) ?>" placeholder="Buscar libros…" aria-label="Texto a buscar">
    <select name="campo" aria-label="Buscar en">
      <?php foreach ($campos as $k => $v): ?><option value="<?= e($k) ?>" <?= $k === $campo ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
    </select>
    <button class="btn btn-primario">Buscar</button>
  </div>
  <div class="filtros">
    <select name="area"><option value="">Todas las áreas</option>
      <?php foreach (valores_distintos('area') as $a): ?><option <?= $a === $area ? 'selected' : '' ?>><?= e($a) ?></option><?php endforeach; ?>
    </select>
    <select name="nivel"><option value="">Todos los niveles</option>
      <?php foreach (valores_distintos('nivel') as $n): ?><option <?= $n === $nivel ? 'selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?>
    </select>
    <label class="check"><input type="checkbox" name="disp" value="1" <?= $soloDisp ? 'checked' : '' ?>> Solo disponibles</label>
    <?php if ($gestion): ?>
      <label class="check"><input type="checkbox" name="inactivos" value="1" <?= $inactivos ? 'checked' : '' ?>> Incluir inactivos</label>
    <?php endif; ?>
    <?php if ($filtros && array_keys($filtros) !== ['campo']): ?><a class="enlace-sec" href="<?= e(url('catalogo')) ?>">Limpiar</a><?php endif; ?>
  </div>
</form>

<?php if (!$materiales): ?>
  <div class="vacio">No se encontraron materiales con esos criterios.</div>
<?php elseif ($gestion): ?>
  <div class="tabla-envoltura">
  <table class="tabla">
    <thead><tr><th>Código</th><th>Título / autor</th><th>Área · nivel</th><th>Ubicación</th>
      <th class="num" title="Total">Total</th><th class="num">Prest.</th><th class="num" title="Deteriorados">Det.</th><th class="num" title="Perdidos">Perd.</th><th>Disponibilidad</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($materiales as $m): ?>
      <tr class="<?= $m['activo'] ? '' : 'inactivo' ?>">
        <td class="mono"><?= e($m['codigo']) ?></td>
        <td><a href="<?= e(url('material_ver', ['id' => $m['id']])) ?>"><strong><?= e($m['titulo']) ?></strong></a><br><small><?= e($m['autor']) ?></small></td>
        <td><?= e($m['area']) ?><br><small><?= e($m['nivel']) ?></small></td>
        <td class="nowrap"><?= e($m['ubicacion'] ?: '—') ?></td>
        <td class="num"><?= (int)$m['cantidad_total'] ?></td>
        <td class="num"><?= (int)$m['prestados'] ?></td>
        <td class="num"><?= (int)$m['cantidad_deteriorada'] ?></td>
        <td class="num"><?= (int)$m['cantidad_perdida'] ?></td>
        <td><?= disponibilidad_html($m) ?></td>
        <td class="acciones-fila"><a href="<?= e(url('material_form', ['id' => $m['id']])) ?>">Editar</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
<?php else: ?>
  <div class="fichas">
  <?php foreach ($materiales as $m): ?>
    <article class="ficha">
      <div class="ficha-cab">
        <span class="etiqueta"><?= e($m['area'] ?: 'General') ?></span>
        <?= disponibilidad_html($m) ?>
      </div>
      <h3><a href="<?= e(url('material_ver', ['id' => $m['id']])) ?>"><?= e($m['titulo']) ?></a></h3>
      <p class="autor"><?= e($m['autor'] ?: 'Autor no registrado') ?></p>
      <?php if ($m['descripcion']): ?><p class="desc"><?= e(mb_strimwidth($m['descripcion'], 0, 160, '…')) ?></p><?php endif; ?>
      <dl class="datos">
        <?php if ($m['tema']): ?><dt>Tema</dt><dd><?= e($m['tema']) ?></dd><?php endif; ?>
        <dt>Editorial</dt><dd><?= e($m['editorial'] ?: '—') ?><?= $m['anio'] ? ', ' . (int)$m['anio'] : '' ?></dd>
        <dt>Nivel</dt><dd><?= e($m['nivel'] ?: '—') ?></dd>
        <dt>Código</dt><dd class="mono"><?= e($m['codigo']) ?></dd>
      </dl>
    </article>
  <?php endforeach; ?>
  </div>
<?php endif; ?>
<?= enlaces_paginacion($total, $limite, $pag, 'catalogo', $filtros) ?>
<?php pagina_fin();
