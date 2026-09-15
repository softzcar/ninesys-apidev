<?php

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Token de aprobación de diseño por el cliente -- auditoría de seguridad
 * 2026-09-15 (Fase A, hallazgo detectado durante la auditoría de
 * `designs.php`). El link de aprobación (`/clientes/aprobacion/{id_orden}`)
 * se comparte hoy con el cliente por WhatsApp, protegido solo por el
 * `id_orden` secuencial y adivinable en la URL -- sin este token, cualquiera
 * que adivine/incremente un `id_orden` podía ver y APROBAR el diseño de la
 * orden de otro cliente. El `id_orden` se mantiene en la URL por
 * compatibilidad/legibilidad; el token es lo que autentica.
 *
 * Sin expiración corta a propósito (a diferencia del token de confirmación
 * de sesión, de 2 minutos, ver SesionUnicaHelper.php): un cliente puede
 * tardar días en revisar y aprobar su diseño, y no hay CAPTCHA/clave de por
 * medio que justifique una ventana corta -- 90 días cubre con margen
 * cualquier ciclo real de producción.
 */
function generarTokenAprobacionCliente(string $secret, int $idOrden): string
{
    $ahora = time();
    $payload = [
        'iss' => 'ninesys-aprobacion-cliente',
        'iat' => $ahora,
        'exp' => $ahora + (86400 * 90),
        'id_orden' => $idOrden,
    ];
    return JWT::encode($payload, $secret, 'HS256');
}

/**
 * Decodifica el token anterior. Nunca lanza excepción hacia afuera --
 * cualquier problema (firma inválida, vencido, malformado, ausente) se trata
 * igual: token no válido, 403.
 */
function decodificarTokenAprobacionCliente(string $secret, string $token): ?object
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
