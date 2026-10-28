<?php
/**
 * Arranque común: configuración, sesión, conexión y funciones auxiliares.
 */
declare(strict_types=1);

date_default_timezone_set('America/Lima');
mb_internal_encoding('UTF-8');

define('RAIZ', dirname(__DIR__));

$archivoConfig = RAIZ . '/config/config.php';
if (!is_file($archivoConfig)) {
    http_response_code(500);
    exit('Falta el archivo config/config.php. Copie config/config.example.php como config.php y complete los datos de la base de datos.');
}
$GLOBALS['CONFIG'] = require $archivoConfig;

if (!empty($GLOBALS['CONFIG']['debug'])) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
      || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
session_name('BIBSJ');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => $https,
]);
session_start();

require RAIZ . '/app/helpers.php';
require RAIZ . '/app/db.php';
require RAIZ . '/app/permisos.php';
require RAIZ . '/app/auth.php';
require RAIZ . '/app/vista.php';
require RAIZ . '/app/catalogo.php';
require RAIZ . '/app/prestamos.php';
