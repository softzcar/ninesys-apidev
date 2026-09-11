<?php

/**
 * Límite de intentos fallidos de /login, contado por email (nunca por IP --
 * ver memoria de seguridad [[project_fase_seguridad_pendiente]]: limitar por
 * IP bloquearía oficinas enteras que comparten una sola IP de salida). Se
 * cuenta por el email tal como se envió, exista o no en el sistema, para no
 * reabrir la enumeración de usuarios cerrada en la Fase 5 (un 429 solo para
 * emails reales ya revelaría que existen).
 *
 * Tabla `login_intentos_fallidos` en la base central `api_empresas` (no es
 * una tabla por-empresa -- el login se resuelve por email antes de saber a
 * qué empresa pertenece).
 */

const LOGIN_INTENTOS_MAXIMOS = 6;
const LOGIN_VENTANA_MINUTOS = 15;
const LOGIN_BLOQUEO_MINUTOS = 15;

/**
 * Devuelve el timestamp (string) de `bloqueado_hasta` si el email está
 * bloqueado en este momento, o null si no lo está (o nunca tuvo intentos).
 */
function estaBloqueado(LocalDB $central, string $email): ?string
{
    $filas = $central->goQuery(
        'SELECT bloqueado_hasta FROM login_intentos_fallidos WHERE email = ? AND bloqueado_hasta > NOW()',
        [$email]
    );

    if (!empty($filas) && !empty($filas[0]['bloqueado_hasta'])) {
        return $filas[0]['bloqueado_hasta'];
    }

    return null;
}

/**
 * Registra un intento fallido para este email. Ventana deslizante simple: si
 * el último registro es más viejo que LOGIN_VENTANA_MINUTOS, se reinicia el
 * contador en vez de acumular indefinidamente. Al llegar a
 * LOGIN_INTENTOS_MAXIMOS, fija `bloqueado_hasta`.
 */
function registrarIntentoFallido(LocalDB $central, string $email): void
{
    $filas = $central->goQuery(
        'SELECT intentos, primer_intento FROM login_intentos_fallidos WHERE email = ?',
        [$email]
    );

    if (empty($filas)) {
        $central->goQuery(
            'INSERT INTO login_intentos_fallidos (email, intentos, primer_intento, bloqueado_hasta) VALUES (?, 1, NOW(), NULL)',
            [$email]
        );
        return;
    }

    $ventanaVencida = $central->goQuery(
        "SELECT (primer_intento < NOW() - INTERVAL '" . LOGIN_VENTANA_MINUTOS . " minutes') AS vencida FROM login_intentos_fallidos WHERE email = ?",
        [$email]
    );
    $vencida = !empty($ventanaVencida) && ($ventanaVencida[0]['vencida'] === true || $ventanaVencida[0]['vencida'] === 't');

    if ($vencida) {
        $central->goQuery(
            'UPDATE login_intentos_fallidos SET intentos = 1, primer_intento = NOW(), bloqueado_hasta = NULL WHERE email = ?',
            [$email]
        );
        return;
    }

    $intentosNuevos = (int) $filas[0]['intentos'] + 1;

    if ($intentosNuevos >= LOGIN_INTENTOS_MAXIMOS) {
        $central->goQuery(
            "UPDATE login_intentos_fallidos SET intentos = ?, bloqueado_hasta = NOW() + INTERVAL '" . LOGIN_BLOQUEO_MINUTOS . " minutes' WHERE email = ?",
            [$intentosNuevos, $email]
        );
    } else {
        $central->goQuery(
            'UPDATE login_intentos_fallidos SET intentos = ? WHERE email = ?',
            [$intentosNuevos, $email]
        );
    }
}

/**
 * Limpia el contador de intentos de un email -- tras un login exitoso, un
 * cambio de clave por un administrador, o un desbloqueo manual explícito.
 */
function limpiarIntentos(LocalDB $central, string $email): void
{
    $central->goQuery('DELETE FROM login_intentos_fallidos WHERE email = ?', [$email]);
}
