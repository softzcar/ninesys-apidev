<?php

/**
 * Autenticación servidor-a-servidor (header `X-Internal-Token`,
 * `hash_equals()`) -- auditoría de seguridad 2026-09-10. Unifica la
 * validación que antes vivía duplicada en `routes.php`
 * (`validarTokenInternoSetup()`) y repetida inline en varios endpoints
 * `/internal/*` de `msg_service.php` (algunos de los cuales no la tenían en
 * absoluto pese a que el comentario de cabecera del archivo lo afirmaba).
 *
 * Un secreto distinto por servicio, cualquiera de los dos es válido: los
 * clientes de msg_ninesys usan `MSG_SERVICE_INTERNAL_TOKEN` y los de
 * 19print_app usan `PRINT_SERVICE_INTERNAL_TOKEN`. El `id_empresa` sigue
 * viajando como hoy (crudo, en `Authorization`), pero ahora autenticado por
 * este secreto en vez de confiado a ciegas.
 */

/**
 * @return bool true si $provided coincide con alguno de los secretos de
 *   servicio configurados (comparación constant-time).
 */
function esTokenInternoValido(string $provided): bool
{
    if ($provided === '') {
        return false;
    }
    $candidatos = array_filter([
        getenv('MSG_SERVICE_INTERNAL_TOKEN') ?: '',
        getenv('PRINT_SERVICE_INTERNAL_TOKEN') ?: '',
    ]);
    foreach ($candidatos as $expected) {
        if (hash_equals($expected, $provided)) {
            return true;
        }
    }
    return false;
}

/**
 * Valida el header `X-Internal-Token` de la request. Devuelve null si es
 * válido (el caller continúa normalmente), o una Response 401 lista para
 * devolver si no lo es -- mismo patrón que ya usaba `validarTokenInternoSetup()`.
 */
function validarTokenInterno(\Psr\Http\Message\ServerRequestInterface $request, \Psr\Http\Message\ResponseInterface $response): ?\Psr\Http\Message\ResponseInterface
{
    $providedToken = $request->getHeaderLine('X-Internal-Token');

    if (!esTokenInternoValido($providedToken)) {
        $response->getBody()->write(json_encode([
            'error' => 'Unauthorized',
            'message' => 'Token interno inválido o ausente.',
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
    }

    return null;
}
