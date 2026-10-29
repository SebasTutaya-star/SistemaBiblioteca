<?php
/** Componentes de presentación. No altera permisos, operaciones ni reglas de negocio. */
function ui_icono(string $nombre): string
{
    $simbolos = ['home'=>'dashboard','book'=>'menu_book','swap'=>'assignment_return','list'=>'assignment','clock'=>'history','bell'=>'notifications','chart'=>'bar_chart','upload'=>'upload_file','sliders'=>'tune','users'=>'group','shield'=>'verified_user','key'=>'lock','logout'=>'logout','search'=>'search','plus'=>'add','arrow'=>'arrow_forward','check'=>'check_circle','info'=>'info','school'=>'school','person'=>'person','mail'=>'mail','back'=>'arrow_back','menu'=>'menu','close'=>'close','shelf'=>'shelves','calendar'=>'calendar_month','more_horiz'=>'more_horiz','download'=>'download'];
    return '<svg class="material-symbols-outlined" width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><use href="#ui-' . ($simbolos[$nombre] ?? 'info') . '"></use></svg>';
}

function ui_portada(array $material, string $clase = ''): string
{
    // Arte exportado del prototipo cuando coincide el título; sin columnas nuevas.
    $portadas = ['cien anos de soledad'=>'cien-anos-de-soledad', 'los rios profundos'=>'los-rios-profundos', 'el principito'=>'el-principito', 'cosmos'=>'cosmos'];
    $clave = mb_strtolower(sin_tildes(trim($material['titulo'])));
    if (isset($portadas[$clave])) {
        return '<div class="libro-portada portada-imagen ' . e($clase) . '" aria-hidden="true"><img src="assets/img/portadas/' . $portadas[$clave] . '.jpg" alt="" loading="lazy"><b>' . e($material['codigo'] ?? '') . '</b></div>';
    }
    // El esquema no contiene portadas: cubierta tipográfica para los demás títulos.
    return '<div class="libro-portada ' . e($clase) . '" aria-hidden="true"><span>' . ui_icono('book') . '</span><strong>' . e($material['titulo']) . '</strong><small>' . e($material['autor'] ?? '') . '</small><b>' . e($material['codigo'] ?? '') . '</b></div>';
}

function ui_metrica(string $titulo, $valor, string $detalle, string $ico = 'book', string $tono = ''): void
{
    echo '<article class="metrica ' . e($tono) . '"><div><span>' . e($titulo) . '</span>' . ui_icono($ico) . '</div><strong>' . e($valor) . '</strong><small>' . e($detalle) . '</small></article>';
}

function ui_cabecera_auth(): void
{
    ?><header class="auth-header"><a class="auth-marca" href="<?= e(url('login')) ?>"><img src="assets/img/insignia-san-juan.png" alt="Insignia de la I.E. San Juan"><span><strong>I.E. San Juan</strong><small>Biblioteca escolar institucional</small></span></a><nav aria-label="Acceso"><a href="<?= e(url('login')) ?>">Ingreso</a><a href="<?= e(url('recuperar')) ?>">Recuperar acceso</a><a href="#ayuda-acceso">Mesa de ayuda</a></nav></header><?php
}

function ui_pie_auth(): void
{
    ?><footer class="auth-footer"><span>I.E. San Juan · <?= date('Y') ?> · Sistema de gestión bibliotecaria</span><span>San Juan Bautista · Ayacucho</span></footer><?php
}
