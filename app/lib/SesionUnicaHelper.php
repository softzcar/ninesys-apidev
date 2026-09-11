<?php

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Sesión única por empleado -- pedido explícito del usuario 2026-09-11 (ver
 * memoria de seguridad [[project_fase_seguridad_pendiente]]): un mismo
 * usuario no puede tener dos sesiones activas a la vez, ni en el mismo
 * dispositivo ni en dispositivos distintos. Al iniciar sesión en un lugar
 * nuevo mientras ya hay una sesión abierta, se le avisa al usuario (con el
 * dispositivo/fecha de esa sesión, mejor esfuerzo vía User-Agent) y solo se
 * cierra la anterior si confirma.
 *
 * Mecanismo: `sesiones_activas` (`id_usuario` PK -- una sola fila por
 * persona, así que a nivel de datos ya es imposible tener dos) guarda el
 * `session_id` aleatorio de la sesión vigente. Ese mismo valor viaja como
 * claim `sid` dentro del JWT (ver JwtHelper.php). Cualquier JWT cuyo `sid` no
 * coincida con el guardado queda invalidado -- así se "cierra" la sesión
 * vieja sin avisarle en tiempo real, simplemente dejando de aceptar su token
 * en la siguiente petición que haga (ver IdEmpresaMiddleware.php).
 */

function generarSessionId(): string
{
    return bin2hex(random_bytes(16));
}

/**
 * Devuelve los datos de la sesión activa de este usuario (dispositivo, fecha,
 * session_id), o null si no tiene ninguna.
 */
function sesionActivaDe(LocalDB $central, int $idUsuario): ?array
{
    $filas = $central->goQuery(
        'SELECT session_id, dispositivo_info, creado_en FROM sesiones_activas WHERE id_usuario = ?',
        [$idUsuario]
    );
    return $filas[0] ?? null;
}

/**
 * Reclama la sesión para este usuario -- reemplaza cualquier sesión anterior
 * (upsert por id_usuario, que es la PK). Se llama SOLO en el punto donde el
 * login ya se decidió exitoso y se va a emitir el JWT -- nunca antes, para no
 * quitarle la sesión a nadie por un login que termina fallando más adelante.
 */
function reclamarSesion(LocalDB $central, int $idUsuario, string $sessionId, string $dispositivoInfo, string $ip): void
{
    $central->goQuery(
        'INSERT INTO sesiones_activas (id_usuario, session_id, dispositivo_info, ip, creado_en)
         VALUES (?, ?, ?, ?, NOW())
         ON CONFLICT (id_usuario) DO UPDATE SET session_id = EXCLUDED.session_id,
             dispositivo_info = EXCLUDED.dispositivo_info, ip = EXCLUDED.ip, creado_en = NOW()',
        [$idUsuario, $sessionId, $dispositivoInfo, $ip]
    );
}

/**
 * True si el session_id del JWT sigue siendo el vigente para ese usuario.
 */
function sesionEsValida(LocalDB $central, int $idUsuario, string $sessionId): bool
{
    if ($sessionId === '') {
        return false;
    }
    $filas = $central->goQuery('SELECT session_id FROM sesiones_activas WHERE id_usuario = ?', [$idUsuario]);
    return !empty($filas) && hash_equals((string) $filas[0]['session_id'], $sessionId);
}

/**
 * Cierra la sesión de este usuario (logout real) -- libera el cupo para que
 * un login futuro (desde donde sea) no pida confirmación innecesaria.
 */
function cerrarSesionDe(LocalDB $central, int $idUsuario): void
{
    $central->goQuery('DELETE FROM sesiones_activas WHERE id_usuario = ?', [$idUsuario]);
}

/**
 * Token corto y de un solo uso que autoriza confirmar "cerrar la otra
 * sesión" sin pedir un nuevo CAPTCHA -- auditoría de seguridad 2026-09-11
 * (el usuario reportó que cada conflicto real de sesión obligaba a verificar
 * Turnstile dos veces). Se emite justo cuando /login detecta el conflicto --
 * en ESE MISMO request ya se probaron Turnstile y la clave correcta, así que
 * reutilizar esa prueba para el reintento no reduce ninguna protección real.
 * Queda atado al `session_id` vigente en ese momento (`sid_objetivo`): en
 * cuanto se reclame la sesión (o cualquier otra cosa la cambie), ese
 * session_id deja de coincidir y el token deja de servir para cualquier
 * conflicto futuro -- de un solo uso sin necesidad de una lista de
 * usados. Vence a los 2 minutos, tiempo de sobra para leer el diálogo de
 * confirmación y responder.
 */
function generarTokenConfirmacionSesion(string $secret, int $idUsuario, string $sessionIdObjetivo): string
{
    $ahora = time();
    $payload = [
        'iss' => 'ninesys-confirmacion-sesion',
        'iat' => $ahora,
        'exp' => $ahora + 120,
        'id_usuario' => $idUsuario,
        'sid_objetivo' => $sessionIdObjetivo,
    ];
    return JWT::encode($payload, $secret, 'HS256');
}

/**
 * Decodifica el token anterior. Nunca lanza excepción hacia afuera --
 * cualquier problema (firma inválida, vencido, malformado, ausente) se trata
 * igual: no hay confirmación previa válida, /login cae al camino normal y
 * exige un cf-turnstile-response fresco.
 */
function decodificarTokenConfirmacionSesion(string $secret, string $token): ?object
{
    if ($secret === '' || $token === '') {
        return null;
    }
    try {
        return JWT::decode($token, new Key($secret, 'HS256'));
    } catch (\Throwable $e) {
        return null;
    }
}

/**
 * Traduce un User-Agent a un texto corto y legible, mejor esfuerzo (no es un
 * fingerprint exacto). Ej. "Chrome en Windows", "Safari en iPhone".
 */
function describirDispositivo(string $userAgent): string
{
    if ($userAgent === '') {
        return 'un dispositivo desconocido';
    }

    if (preg_match('/iPhone/i', $userAgent)) {
        $so = 'iPhone';
    } elseif (preg_match('/iPad/i', $userAgent)) {
        $so = 'iPad';
    } elseif (preg_match('/Android/i', $userAgent)) {
        $so = 'Android';
    } elseif (preg_match('/Windows/i', $userAgent)) {
        $so = 'Windows';
    } elseif (preg_match('/Macintosh|Mac OS X/i', $userAgent)) {
        $so = 'Mac';
    } elseif (preg_match('/Linux/i', $userAgent)) {
        $so = 'Linux';
    } else {
        $so = 'un dispositivo desconocido';
    }

    if (preg_match('/Edg\//i', $userAgent)) {
        $navegador = 'Edge';
    } elseif (preg_match('/OPR\/|Opera/i', $userAgent)) {
        $navegador = 'Opera';
    } elseif (preg_match('/Chrome\//i', $userAgent) && !preg_match('/Chromium/i', $userAgent)) {
        $navegador = 'Chrome';
    } elseif (preg_match('/Firefox\//i', $userAgent)) {
        $navegador = 'Firefox';
    } elseif (preg_match('/Safari\//i', $userAgent) && !preg_match('/Chrome/i', $userAgent)) {
        $navegador = 'Safari';
    } else {
        $navegador = null;
    }

    return $navegador ? "{$navegador} en {$so}" : $so;
}
