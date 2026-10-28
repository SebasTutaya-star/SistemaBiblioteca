<?php
/**
 * Autenticación (RF-01, RNF-01).
 */

const MAX_INTENTOS = 5;
const MINUTOS_BLOQUEO = 10;

function usuario(): ?array
{
    static $u = false;
    if ($u === false) {
        $u = null;
        if (!empty($_SESSION['uid'])) {
            $u = fila('SELECT id, dni, nombres, apellidos, correo, rol, tipo_lector, grado, seccion, activo, debe_cambiar_clave
                       FROM usuarios WHERE id = ?', [$_SESSION['uid']]);
            if (!$u || !$u['activo']) {   // cuenta desactivada mientras estaba en sesión
                $u = null;
                session_unset();
            }
        }
    }
    return $u;
}

/** Intenta iniciar sesión. Devuelve null si tuvo éxito o un mensaje de error. */
function intentar_login(string $correo, string $clave): ?string
{
    $correo = mb_strtolower(trim($correo));
    $u = fila('SELECT * FROM usuarios WHERE correo = ?', [$correo]);
    $generico = 'Correo o contraseña incorrectos.';

    if (!$u) {
        password_verify($clave, '$2y$10$IkiFuIrHi7U.eEHBJcDmpu5o1NZ/dDD3ezgcQzI4vge5o9emI1JSa'); // tiempo constante
        return $generico;
    }
    if ($u['bloqueado_hasta'] && strtotime($u['bloqueado_hasta']) > time()) {
        return 'Demasiados intentos fallidos. Espere ' . MINUTOS_BLOQUEO . ' minutos e intente nuevamente.';
    }
    if (!password_verify($clave, $u['clave_hash'])) {
        $intentos = $u['intentos_fallidos'] + 1;
        $bloqueo = $intentos >= MAX_INTENTOS ? date('Y-m-d H:i:s', time() + MINUTOS_BLOQUEO * 60) : null;
        q('UPDATE usuarios SET intentos_fallidos = ?, bloqueado_hasta = ? WHERE id = ?',
          [$bloqueo ? 0 : $intentos, $bloqueo, $u['id']]);
        return $generico;
    }
    if (!$u['activo']) {
        return 'Su cuenta está desactivada. Comuníquese con el Administrador.';
    }

    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$u['id'];
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
    q('UPDATE usuarios SET intentos_fallidos = 0, bloqueado_hasta = NULL, ultimo_acceso = NOW() WHERE id = ?', [$u['id']]);
    if (password_needs_rehash($u['clave_hash'], PASSWORD_DEFAULT)) {
        q('UPDATE usuarios SET clave_hash = ? WHERE id = ?', [password_hash($clave, PASSWORD_DEFAULT), $u['id']]);
    }
    return null;
}

function cerrar_sesion(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/** Reglas mínimas de contraseña. Devuelve mensaje de error o null. */
function validar_clave_nueva(string $clave, string $confirmacion, ?array $u = null): ?string
{
    if (mb_strlen($clave) < 8) return 'La contraseña debe tener al menos 8 caracteres.';
    if (!preg_match('/[A-Za-zÁÉÍÓÚáéíóúÑñ]/u', $clave) || !preg_match('/\d/', $clave)) {
        return 'La contraseña debe combinar letras y números.';
    }
    if ($u && $clave === $u['dni']) return 'La nueva contraseña no puede ser su DNI.';
    if ($clave !== $confirmacion) return 'La confirmación no coincide con la nueva contraseña.';
    return null;
}

function enviar_correo(string $para, string $asunto, string $cuerpo): bool
{
    $de = $GLOBALS['CONFIG']['correo_remitente'] ?? 'no-responder@localhost';
    $cab = "From: Biblioteca I.E.P. San Juan <$de>\r\n"
         . "Reply-To: $de\r\n"
         . "MIME-Version: 1.0\r\n"
         . "Content-Type: text/plain; charset=UTF-8\r\n";
    $asunto = '=?UTF-8?B?' . base64_encode($asunto) . '?=';
    return @mail($para, $asunto, $cuerpo, $cab, '-f' . $de);
}
