<?php
/**
 * Configuración del sistema.
 * 1. Copie este archivo como config.php (en la misma carpeta).
 * 2. Complete los datos de la base de datos creada en cPanel (Bases de datos MySQL).
 * config.php NO debe compartirse ni subirse a repositorios públicos.
 */
return [
    'app_nombre'  => 'Biblioteca I.E.P. San Juan',
    // URL pública sin barra final. Ej.: https://biblioteca.iesanjuan.edu.pe
    'url_base'    => 'https://biblioteca.iesanjuan.edu.pe',
    'debug'       => false, // true solo en pruebas: muestra errores en pantalla

    'db' => [
        'host' => 'localhost',
        'name' => 'bbsjuan_biblioteca',   // cPanel antepone el usuario: usuario_nombre
        'user' => 'bbsjuan_biblio',
        'pass' => 'CAMBIE_ESTA_CLAVE',
    ],

    // Correo remitente para la recuperación de contraseña (RF-01).
    // Cree esta cuenta en cPanel > Cuentas de correo electrónico.
    'correo_remitente' => 'no-responder@iesanjuan.edu.pe',

    // Exigir cambio de contraseña en el primer ingreso (la contraseña inicial es el DNI)
    'forzar_cambio_clave_inicial' => true,
];
