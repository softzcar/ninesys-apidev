<?php

/**
 * Verificación de Cloudflare Turnstile (CAPTCHA) -- auditoría de seguridad
 * 2026-09-11, protección contra fuerza bruta en /login (ver memoria de
 * seguridad [[project_fase_seguridad_pendiente]]). Junto con
 * LoginIntentosHelper.php, cierra el hallazgo de que /login no tenía ningún
 * límite ni verificación anti-bot.
 */

/**
 * Verifica un token de Turnstile contra la API de Cloudflare. Nunca lanza
 * excepción -- un error de red/config se trata como verificación fallida
 * (falla cerrado, no abierto).
 */
function verificarTurnstile(string $token, string $ip): bool
{
    if ($token === '') {
        return false;
    }

    $secret = getenv('TURNSTILE_SECRET_KEY') ?: '';
    if ($secret === '') {
        error_log('[Turnstile] TURNSTILE_SECRET_KEY no configurado -- verificación fallando cerrado.');
        return false;
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://challenges.cloudflare.com/turnstile/v0/siteverify');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'secret' => $secret,
        'response' => $token,
        'remoteip' => $ip,
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // A diferencia de otras llamadas salientes del proyecto (ej.
    // tasas_cambio.php), acá SÍ se valida el certificado -- es una llamada
    // de seguridad, no un scraper.
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);

    $result = curl_exec($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($result === false) {
        error_log('[Turnstile] Error de conexión al verificar: ' . $curlError);
        return false;
    }

    $decoded = json_decode($result, true);
    return isset($decoded['success']) && $decoded['success'] === true;
}
