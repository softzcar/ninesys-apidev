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

/**
 * Autorización por departamento -- auditoría de seguridad 2026-09-14.
 * Mismo patrón que requiereAdmin(): 401 si no hay sesión JWT real, 403 si la
 * sesión es real pero el empleado no pertenece a $idDepartamento.
 * Administrador (`acceso=1`) siempre pasa -- mismo criterio que ya usa el
 * frontend (las páginas de departamento aceptan `id_modulo` propio O el de
 * Administración).
 *
 * `DEPARTAMENTOS_TOKEN` viaja en el JWT desde el login (ver JwtHelper.php);
 * si el empleado cambia de departamento a mitad de sesión, se refleja recién
 * en el próximo login (sin refresh token, mismo precedente ya aceptado con
 * `acceso`).
 */
function perteneceADepartamento(\Psr\Http\Message\ServerRequestInterface $request, \Psr\Http\Message\ResponseInterface $response, int $idDepartamento): ?\Psr\Http\Message\ResponseInterface
{
    if (!defined('ID_USUARIO_TOKEN')) {
        $response->getBody()->write(json_encode([
            'error' => 'invalid_token',
            'message' => 'Sesión inválida o expirada. Debe iniciar sesión nuevamente.',
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
    }

    if ((int) (defined('ACCESO_TOKEN') ? ACCESO_TOKEN : 0) === 1) {
        return null;
    }

    $departamentos = defined('DEPARTAMENTOS_TOKEN') ? DEPARTAMENTOS_TOKEN : [];
    if (!in_array($idDepartamento, array_map('intval', (array) $departamentos), true)) {
        $response->getBody()->write(json_encode([
            'error' => 'forbidden',
            'message' => 'No pertenece al departamento correspondiente a esta acción.',
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
    }

    return null;
}

/**
 * Autorización por módulo -- auditoría de seguridad 2026-09-14 (fase 2:
 * autorización por módulo/página, ver memoria de seguridad
 * [[project_fase_seguridad_pendiente]]). Mismo patrón que
 * perteneceADepartamento(): 401 si no hay sesión JWT real, 403 si la sesión
 * es real pero el empleado no tiene ningún departamento perteneciente a
 * $idModulo, admin (`acceso=1`) siempre pasa.
 *
 * `$idModulo` es `departamentos.id_modulo` (tabla central
 * `api_empresas.modulos`) -- NUNCA un nombre de departamento en texto. El
 * frontend ya usa este mismo id_modulo hoy para mostrar/ocultar páginas
 * (`accessModule.accessData.id_modulo`); esta guardia valida lo mismo en el
 * backend por ID, sin depender de comparar strings como
 * `departamento === 'Administración'` (frágil: no es estable entre empresas
 * ni resistente a un rename, y ya generó al menos un bug real de confusión
 * con `departamentos._id` -- ver 2026-08-03 en el código del frontend). Un
 * mapeo de referencia (puede variar por empresa, no asumir fijo sin
 * confirmar contra `api_empresas.modulos`): 1=Administración,
 * 2=Comercialización, 3=Diseño, 4=Producción (línea), 5=Producción
 * (genérico).
 *
 * `MODULOS_TOKEN` viaja en el JWT desde el login (ver JwtHelper.php), mismo
 * precedente ya aceptado que `DEPARTAMENTOS_TOKEN`/`ACCESO_TOKEN`: si el
 * departamento del empleado cambia a mitad de sesión, se refleja recién en
 * el próximo login.
 */
function perteneceAModulo(\Psr\Http\Message\ServerRequestInterface $request, \Psr\Http\Message\ResponseInterface $response, int $idModulo): ?\Psr\Http\Message\ResponseInterface
{
    if (!defined('ID_USUARIO_TOKEN')) {
        $response->getBody()->write(json_encode([
            'error' => 'invalid_token',
            'message' => 'Sesión inválida o expirada. Debe iniciar sesión nuevamente.',
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
    }

    if ((int) (defined('ACCESO_TOKEN') ? ACCESO_TOKEN : 0) === 1) {
        return null;
    }

    $modulos = defined('MODULOS_TOKEN') ? MODULOS_TOKEN : [];
    if (!in_array($idModulo, array_map('intval', (array) $modulos), true)) {
        $response->getBody()->write(json_encode([
            'error' => 'forbidden',
            'message' => 'No pertenece al módulo correspondiente a esta acción.',
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
    }

    return null;
}

/**
 * Variante de perteneceAModulo() para páginas/endpoints que el frontend
 * expone a MÁS DE UN módulo a la vez (patrón muy común en el frontend, ej.
 * `id_modulo === 2 || id_modulo === 1`) -- pasa si el empleado pertenece a
 * CUALQUIERA de los módulos de `$idsModulo`, o es admin. Mismos códigos de
 * error que perteneceAModulo().
 */
function perteneceAAlgunModulo(\Psr\Http\Message\ServerRequestInterface $request, \Psr\Http\Message\ResponseInterface $response, array $idsModulo): ?\Psr\Http\Message\ResponseInterface
{
    if (!defined('ID_USUARIO_TOKEN')) {
        $response->getBody()->write(json_encode([
            'error' => 'invalid_token',
            'message' => 'Sesión inválida o expirada. Debe iniciar sesión nuevamente.',
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
    }

    if ((int) (defined('ACCESO_TOKEN') ? ACCESO_TOKEN : 0) === 1) {
        return null;
    }

    $modulos = array_map('intval', (array) (defined('MODULOS_TOKEN') ? MODULOS_TOKEN : []));
    foreach ($idsModulo as $idModulo) {
        if (in_array((int) $idModulo, $modulos, true)) {
            return null;
        }
    }

    $response->getBody()->write(json_encode([
        'error' => 'forbidden',
        'message' => 'No pertenece al módulo correspondiente a esta acción.',
    ]));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
}
