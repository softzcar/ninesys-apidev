<?php

/**
 * Autorización por rol -- auditoría de seguridad 2026-09-10 (Fase 4, cierra
 * el hallazgo A6: "Autorización solo en el navegador; localStorage editable
 * da rol admin", ver memoria de seguridad [[project_fase_seguridad_pendiente]]).
 *
 * El modelo real del sistema es simple: un flag binario `acceso` (0 =
 * Empleado, 1 = Administrador) en `empresas_usuarios.acceso`, que la sesión
 * JWT ya trae como claim desde la Fase 2 (ver JwtHelper.php) y que
 * IdEmpresaMiddleware.php ya expone como la constante `ACCESO_TOKEN` en modo
 * sesión -- hasta ahora, sin que nada la usara. No hay roles granulares ni
 * tabla de permisos en este sistema; no se inventa ninguna acá.
 */

/**
 * Exige una sesión JWT real con acceso=1 (Administrador). Devuelve null si
 * corresponde (el caller continúa normalmente), o una Response lista para
 * devolver si no -- mismo patrón que validarTokenInterno()/exigirSesionCdn().
 *
 * 401 si no hay sesión de usuario real (modo servicio/legado no cuentan --
 * no representan a una persona con un nivel de acceso). 403 si la sesión es
 * real pero no es de administrador.
 */
function requiereAdmin(\Psr\Http\Message\ServerRequestInterface $request, \Psr\Http\Message\ResponseInterface $response): ?\Psr\Http\Message\ResponseInterface
{
    if (!defined('ID_USUARIO_TOKEN')) {
        $response->getBody()->write(json_encode([
            'error' => 'invalid_token',
            'message' => 'Sesión inválida o expirada. Debe iniciar sesión nuevamente.',
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
    }

    if ((int) (defined('ACCESO_TOKEN') ? ACCESO_TOKEN : 0) !== 1) {
        $response->getBody()->write(json_encode([
            'error' => 'forbidden',
            'message' => 'Esta acción requiere permisos de administrador.',
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
    }

    return null;
}
