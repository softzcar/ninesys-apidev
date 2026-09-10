<?php

/**
 * Hash de contraseñas -- auditoría de seguridad 2026-09-10 (Fase 3, ver
 * memoria de seguridad [[project_fase_seguridad_pendiente]]). Reemplaza la
 * comparación en texto plano (`===`/`WHERE password = ?`) usada en todo el
 * sistema hasta ahora. Migración transparente: no hay reseteo masivo ni
 * ventana de mantenimiento -- cada login exitoso con una clave todavía en
 * texto plano la rehashea en el momento (ver uso en auth.php).
 */

const PASSWORD_HASH_PREFIXES = ['$2y$', '$2a$', '$2b$', '$argon2'];

function pareceClaveHasheada(string $valor): bool
{
    foreach (PASSWORD_HASH_PREFIXES as $prefijo) {
        if (strncmp($valor, $prefijo, strlen($prefijo)) === 0) {
            return true;
        }
    }
    return false;
}

function hashearClave(string $clave): string
{
    return password_hash($clave, PASSWORD_DEFAULT);
}

/**
 * Verifica una clave contra el valor almacenado, sea que ya esté hasheado
 * o siga en texto plano (legado, todavía no migrado). Nunca compara con
 * `===` -- `password_verify()` ya es de tiempo constante, y el camino de
 * texto plano usa `hash_equals()` (mismo criterio que el resto del proyecto
 * para secretos, ver InternalAuth.php).
 */
function verificarClave(string $clavePlana, string $claveAlmacenada): bool
{
    if (pareceClaveHasheada($claveAlmacenada)) {
        return password_verify($clavePlana, $claveAlmacenada);
    }
    return hash_equals($claveAlmacenada, $clavePlana);
}

/**
 * True si el valor almacenado todavía necesita migrarse a hash (texto
 * plano legado), o si ya es un hash pero con un algoritmo/costo
 * desactualizado (PASSWORD_DEFAULT cambió desde que se generó).
 */
function necesitaRehash(string $claveAlmacenada): bool
{
    if (!pareceClaveHasheada($claveAlmacenada)) {
        return true;
    }
    return password_needs_rehash($claveAlmacenada, PASSWORD_DEFAULT);
}
