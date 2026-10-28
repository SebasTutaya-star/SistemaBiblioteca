<?php
// RF-02: roles y funcionalidades que corresponden a cada uno. El rol de cada cuenta se asigna en "Usuarios".
$conteo = [];
foreach (filas('SELECT rol, COUNT(*) n FROM usuarios WHERE activo = 1 GROUP BY rol') as $r) $conteo[$r['rol']] = $r['n'];
pagina_inicio('Roles y permisos', 'roles');
encabezado('Roles y permisos', 'Funcionalidades que ve cada rol. Para cambiar el rol de una persona, edite su cuenta en Usuarios.');
?>
<div class="ayuda-panel" style="margin-bottom:20px"><h3><?= icono('shield') ?> Perfiles de acceso de la biblioteca</h3><p>Consulta las funciones habilitadas para cada rol. Para asignar otro perfil a una persona, actualiza su cuenta desde Usuarios.</p><a class="btn" href="<?= e(url('usuarios')) ?>">Gestionar usuarios</a></div>
<div class="tabla-envoltura tarjeta"><table class="tabla matriz">
  <thead><tr><th>Funcionalidad</th><th>Requisito</th>
    <?php foreach (ROLES as $k => $v): ?><th class="centro"><?= e($v) ?><br><small><?= (int)($conteo[$k] ?? 0) ?> activa(s)</small></th><?php endforeach; ?></tr></thead>
  <tbody>
    <tr><td>Iniciar sesión, cambiar y recuperar contraseña</td><td>RF-01</td><?php foreach (ROLES as $k => $v): ?><td class="centro si">✓</td><?php endforeach; ?></tr>
    <?php foreach (PERMISOS as [$roles, $desc, $rf]): ?>
      <tr><td><?= e($desc) ?></td><td><?= e($rf) ?></td>
        <?php foreach (ROLES as $k => $v): ?><td class="centro <?= in_array($k, $roles, true) ? 'si' : 'no' ?>"><?= in_array($k, $roles, true) ? '✓' : '—' ?></td><?php endforeach; ?>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table></div>
<p class="nota">Los permisos se verifican en el servidor en cada página, no solo ocultando opciones del menú.</p>
<?php pagina_fin();
