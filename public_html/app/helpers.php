<?php
/**
 * Funciones auxiliares generales.
 */

/** Escapa texto para HTML (previene XSS). */
function e($texto): string
{
    return htmlspecialchars((string)($texto ?? ''), ENT_QUOTES, 'UTF-8');
}

function url(string $pagina = '', array $params = []): string
{
    $q = $pagina !== '' ? ['p' => $pagina] + $params : $params;
    return 'index.php' . ($q ? '?' . http_build_query($q) : '');
}

function redirigir(string $pagina = '', array $params = []): void
{
    header('Location: ' . url($pagina, $params));
    exit;
}

function flash(string $tipo, string $mensaje): void
{
    $_SESSION['flash'][] = ['tipo' => $tipo, 'msg' => $mensaje];
}

function tomar_flash(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function g(string $clave, $defecto = '')
{
    $v = $_GET[$clave] ?? $defecto;
    return is_string($v) ? trim($v) : $defecto;
}

function p(string $clave, $defecto = '')
{
    $v = $_POST[$clave] ?? $defecto;
    return is_string($v) ? trim($v) : $defecto;
}

function es_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/* ---------------- Protección CSRF ---------------- */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_campo(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_verificar(): void
{
    $t = $_POST['_csrf'] ?? '';
    if (!is_string($t) || !hash_equals(csrf_token(), $t)) {
        http_response_code(400);
        exit('La sesión del formulario expiró. Vuelva atrás, recargue la página e intente de nuevo.');
    }
}

/* ---------------- Fechas ---------------- */
function hoy(): string
{
    return date('Y-m-d');
}

function fecha_es(?string $f, bool $conHora = false): string
{
    if (!$f) return '—';
    $t = strtotime($f);
    return $t ? date($conHora ? 'd/m/Y H:i' : 'd/m/Y', $t) : '—';
}

function fecha_valida(string $f): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $f);
    return $d && $d->format('Y-m-d') === $f;
}

function dias_entre(string $desde, string $hasta): int
{
    return (int)((strtotime($hasta) - strtotime($desde)) / 86400);
}

/* ---------------- Textos ---------------- */
function nombre_completo(array $u): string
{
    return trim(($u['nombres'] ?? '') . ' ' . ($u['apellidos'] ?? ''));
}

function sin_tildes(string $s): string
{
    $s = strtr($s, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n',
                    'Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ü'=>'U','Ñ'=>'N']);
    return $s;
}

/** Normaliza un código: mayúsculas, sin espacios dobles. */
function normalizar_codigo(string $c): string
{
    $c = mb_strtoupper(trim($c), 'UTF-8');
    return preg_replace('/\s+/', ' ', $c);
}

/** Lista de areas/niveles existentes para filtros. */
function valores_distintos(string $campo): array
{
    if (!in_array($campo, ['area', 'nivel'], true)) return [];
    return array_column(filas("SELECT DISTINCT $campo AS v FROM materiales WHERE $campo IS NOT NULL AND $campo <> '' ORDER BY $campo"), 'v');
}

/** Paginación simple. Devuelve [offset, limite, pagina]. */
function paginacion(int $porPagina = 25): array
{
    $pag = max(1, (int)g('pag', '1'));
    return [($pag - 1) * $porPagina, $porPagina, $pag];
}

function enlaces_paginacion(int $total, int $porPagina, int $pag, string $pagina, array $params): string
{
    $paginas = (int)ceil($total / $porPagina);
    if ($paginas <= 1) return '';
    $h = '<nav class="paginacion">';
    if ($pag > 1) $h .= '<a href="' . e(url($pagina, ['pag' => $pag - 1] + $params)) . '">‹ Anterior</a>';
    $h .= '<span>Página ' . $pag . ' de ' . $paginas . '</span>';
    if ($pag < $paginas) $h .= '<a href="' . e(url($pagina, ['pag' => $pag + 1] + $params)) . '">Siguiente ›</a>';
    return $h . '</nav>';
}

function json_respuesta($datos): void
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Envía un archivo para descargar y termina. */
function descargar(string $contenido, string $archivo, string $tipo): void
{
    header('Content-Type: ' . $tipo);
    header('Content-Disposition: attachment; filename="' . $archivo . '"');
    header('Content-Length: ' . strlen($contenido));
    header('Cache-Control: no-store');
    echo $contenido;
    exit;
}
