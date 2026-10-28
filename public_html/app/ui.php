<?php
/** Componentes de presentación. No altera permisos, operaciones ni reglas de negocio. */
function ui_icono(string $nombre): string
{
    $simbolos = ['home'=>'dashboard','book'=>'menu_book','swap'=>'assignment_return','list'=>'assignment','clock'=>'history','bell'=>'notifications','chart'=>'bar_chart','upload'=>'upload_file','sliders'=>'tune','users'=>'group','shield'=>'verified_user','key'=>'lock','logout'=>'logout','search'=>'search','plus'=>'add','arrow'=>'arrow_forward','check'=>'check_circle','info'=>'info','school'=>'school','person'=>'person','mail'=>'mail','back'=>'arrow_back','menu'=>'menu','close'=>'close','shelf'=>'shelves','calendar'=>'calendar_month'];
    return '<span class="material-symbols-outlined" aria-hidden="true">' . ($simbolos[$nombre] ?? e($nombre)) . '</span>';
}

function ui_portada(array $material, string $clase = ''): string
{
    // El esquema original no contiene portadas: cubierta tipográfica con datos reales.
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
