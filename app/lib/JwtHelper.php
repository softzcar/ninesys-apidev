<?php

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Sesión real de usuario (JWT) -- auditoría de seguridad 2026-09-10, reemplaza
 * gradualmente el header `Authorization: <id_empresa>` crudo. Sin refresh
 * token (decisión de producto): al expirar, el usuario vuelve a loguearse.
 * Ver memoria de seguridad [[project_fase_seguridad_pendiente]] (hallazgo C2).
 */

/**
 * Genera el JWT de sesión emitido por /login tras una autenticación exitosa.
 *
 * @param array $usuario Fila de empresas_usuarios ya autenticada (requiere
 *   id_usuario, email, acceso -- los mismos campos que ya usa auth.php al
 *   armar la respuesta de /login).
 * @param int $idEmpresa Empresa a la que pertenece la sesión.
 * @param string $sessionId Identificador de sesión única -- auditoría de
 *   seguridad 2026-09-11 (ver SesionUnicaHelper.php). IdEmpresaMiddleware
 *   invalida cualquier JWT cuyo `sid` ya no coincida con el guardado en
 *   `sesiones_activas` (otra sesión lo reemplazó).
 * @param array $idsDepartamentos IDs de los departamentos asignados al
 *   empleado (auditoría de seguridad 2026-09-14, autorización por
 *   departamento) -- IdEmpresaMiddleware los expone como
 *   `DEPARTAMENTOS_TOKEN` para `perteneceADepartamento()` (AuthzHelper.php).
 *   Igual que `acceso`: si el departamento del empleado cambia a mitad de
 *   sesión, se refleja recién en el próximo login (sin refresh token, mismo
 *   precedente ya aceptado).
 * @param array $idsModulos IDs de módulo (`departamentos.id_modulo`, tabla
 *   central `api_empresas.modulos`) derivados de los mismos departamentos del
 *   empleado -- auditoría de seguridad 2026-09-14 (autorización por
 *   módulo/página). IdEmpresaMiddleware los expone como `MODULOS_TOKEN` para
 *   `perteneceAModulo()` (AuthzHelper.php). Es un dato DISTINTO de
 *   `id_departamento`: un mismo módulo agrupa varios departamentos (ej. las
 *   4 estaciones de línea de Producción comparten `id_modulo`), y es lo que
 *   el frontend ya usa hoy (`accessModule.accessData.id_modulo`) para
 *   mostrar/ocultar páginas -- este claim permite validar lo mismo en el
 *   backend, por ID, en vez de solo confiar en el cliente.
 */
function generarJwtSesion(array $usuario, int $idEmpresa, string $sessionId, array $idsDepartamentos = [], array $idsModulos = []): string
{
    $secret = getenv('JWT_SECRET') ?: '';
    $ttlHoras = (float) (getenv('JWT_TTL_HOURS') ?: 24);
    $ahora = time();

    $payload = [
        'iss' => 'ninesys-api',
        'sub' => (string) $usuario['id_usuario'],
        'iat' => $ahora,
        'exp' => $ahora + (int) round($ttlHoras * 3600),
        'id_usuario' => (int) $usuario['id_usuario'],
        'id_empresa' => $idEmpresa,
        'email' => $usuario['email'] ?? null,
        'acceso' => isset($usuario['acceso']) ? (int) $usuario['acceso'] : null,
        'sid' => $sessionId,
        'departamentos' => array_values(array_map('intval', $idsDepartamentos)),
        'modulos' => array_values(array_unique(array_map('intval', $idsModulos))),
    ];

    return JWT::encode($payload, $secret, 'HS256');
}

/**
 * Valida un JWT de sesión y devuelve sus claims como array asociativo, o
 * null si es inválido, expirado, o está mal firmado. Nunca lanza excepción
 * hacia afuera -- IdEmpresaMiddleware decide qué responder (401).
 */
function validarJwtSesion(string $jwt): ?array
{
    $secret = getenv('JWT_SECRET') ?: '';
    if ($secret === '' || $jwt === '') {
        return null;
    }
    try {
        $decoded = JWT::decode($jwt, new Key($secret, 'HS256'));
        return (array) $decoded;
    } catch (\Throwable $e) {
        // Firma inválida, ExpiredSignatureException, formato roto, etc. --
        // todas se tratan igual: sesión no válida.
        return null;
    }
}
