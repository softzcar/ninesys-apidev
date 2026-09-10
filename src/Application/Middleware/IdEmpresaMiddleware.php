<?php declare(strict_types=1);

namespace App\Application\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use PDO;

class IdEmpresaMiddleware implements Middleware
{
    public function process(Request $request, RequestHandler $handler): Response
    {
        // Auditoría de seguridad 2026-09-10 (hallazgo C2, ver memoria de
        // seguridad [[project_fase_seguridad_pendiente]]): el header
        // `Authorization` era literalmente el id_empresa en texto plano, sin
        // firma -- cualquiera que lo adivinara/incrementara obtenía acceso
        // completo a esa empresa. Reemplazado gradualmente por 3 modos
        // (transición: el modo legado sigue funcionando mientras los 4 repos
        // clientes migran, ver plan de despliegue en la memoria):
        //
        //   1) X-Internal-Token válido -> modo SERVICIO (msg_ninesys/
        //      19print_app, llamadas servidor-a-servidor multi-tenant que
        //      necesitan pasar id_empresa como parámetro, no una sesión de
        //      usuario). El Authorization crudo sigue siendo el id_empresa,
        //      pero ahora autenticado por el secreto compartido.
        //   2) `Authorization: Bearer <jwt>` -> modo SESIÓN (app_multi). Se
        //      deriva id_empresa/id_usuario del token firmado, nunca del
        //      header crudo. Un JWT inválido/expirado es 401 inmediato --
        //      NUNCA cae al modo legado (evita una ambigüedad de seguridad
        //      innecesaria y le da al frontend la señal clara de reloguearse).
        //   3) Cualquier otra cosa -> modo LEGADO, comportamiento IDÉNTICO al
        //      actual (id_empresa crudo, sin firma). Se loguea cada uso para
        //      poder confirmar más adelante cuándo ya no hay tráfico en este
        //      modo antes de retirarlo.
        $authHeaderRaw = isset($request->getHeader('Authorization')[0]) ? $request->getHeader('Authorization')[0] : '';
        $internalToken = $request->getHeaderLine('X-Internal-Token');

        if ($internalToken !== '' && esTokenInternoValido($internalToken)) {
            $id_empresa = $authHeaderRaw !== '' ? (int) $authHeaderRaw : null;
        } elseif (stripos($authHeaderRaw, 'Bearer ') === 0) {
            $jwt = trim(substr($authHeaderRaw, 7));
            $claims = validarJwtSesion($jwt);
            if ($claims === null) {
                $response = new \Slim\Psr7\Response();
                $response->getBody()->write(json_encode([
                    'error' => 'invalid_token',
                    'message' => 'Sesión inválida o expirada. Debe iniciar sesión nuevamente.',
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
            }
            $id_empresa = (int) ($claims['id_empresa'] ?? 0);
            define('ID_USUARIO_TOKEN', (int) ($claims['id_usuario'] ?? 0));
            define('ACCESO_TOKEN', $claims['acceso'] ?? null);
        } else {
            $id_empresa = $authHeaderRaw !== '' ? (int) $authHeaderRaw : null;
            error_log('[auth_mode=legacy] ' . $request->getUri()->getPath());
        }

        define('ID_EMPRESA', $id_empresa);

        if ($id_empresa != '0') {
            $dsn = EMPRESAS_DNS;
            $user = EMPRESAS_USER;
            $password = EMPRESAS_PASS;

            try {
                $driver = getenv('DB_DRIVER') ?: 'mysql';
                if ($driver === 'pgsql') {
                    $pdo = new PDO($dsn, $user, $password);
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    $pdo->exec("SET client_encoding TO 'UTF8';");
                } else {
                    $pdo = new PDO($dsn, $user, $password, [
                        PDO::MYSQL_ATTR_INIT_COMMAND => "SET lc_time_names = 'es_ES', NAMES utf8"
                    ]);
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                }

                // paises_soportados (catálogo de plataforma del rediseño de monedas)
                // solo existe hoy en la BD central Postgres -- Producción sigue en
                // MySQL para la BD central, así que el JOIN se hace condicional al
                // driver para no romper cada request autenticada ahí.
                if ($driver === 'pgsql') {
                    $sql = 'SELECT e.db_host, e.db_user, e.db_password, e.nombre, e.db_name, e.pais, e.timezone, e.id_pais, ps.timezone AS timezone_pais
                            FROM empresas e
                            LEFT JOIN paises_soportados ps ON ps.id_pais = e.id_pais
                            WHERE e.id_empresa = :id_empresa';
                } else {
                    $sql = 'SELECT db_host, db_user, db_password, nombre, db_name, pais, timezone, NULL AS id_pais, NULL AS timezone_pais FROM empresas WHERE id_empresa = :id_empresa';
                }
                $stmt = $pdo->prepare($sql);
                $stmt->execute(['id_empresa' => $id_empresa]);

                $connectionDetails = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // --- VALIDACIÓN DE ISOLACIÓN DE SERVIDOR ---
                $serverEnv = getenv('SERVER_ENV') ?: 'production';
                $isDevRequest = (strpos($_SERVER['HTTP_HOST'] ?? '', 'nineteengreen.com') !== false || $serverEnv === 'development');
                
                // Si estamos en desarrollo/test, solo permitimos empresas que apunten a dominios de desarrollo
                // o que tengan una marca específica si la agregamos después.
                // Por ahora, validamos contra el ID de empresa si es necesario, 
                // o simplemente nos aseguramos que LOCAL_DNS no apunte a un host externo inesperado.
                
                if ($connectionDetails) {
                    $pais = $connectionDetails['pais'] ?? null;
                    // Resolución en capas (nunca se asume en silencio):
                    // 1) override explícito de la empresa (empresas.timezone,
                    //    editable desde ConfigTimezoneForm.vue);
                    // 2) zona real del país configurado (paises_soportados,
                    //    vía empresas.id_pais -- la fuente correcta);
                    // 3) mapa legado por nombre de país (empresas.pais es en
                    //    realidad un código telefónico, casi nunca coincide,
                    //    queda solo como último recurso para empresas sin
                    //    id_pais fijado todavía);
                    // 4) default final.
                    $timezone = $connectionDetails['timezone'] ?? null;
                    if (empty($timezone)) {
                        $timezone = $connectionDetails['timezone_pais'] ?? null;
                    }
                    if (empty($timezone)) {
                        $timezone = $this->getTimezoneByCountry($pais);
                    }
                    date_default_timezone_set($timezone);
                    define('EMPRESA_TIMEZONE', $timezone);
                    $targetHost = $connectionDetails['db_host'];
                    // Si el servidor es 'development' pero la base de datos de la empresa no es local ni del dominio dev,
                    // podríamos bloquearlo. Pero lo más seguro es confiar en que el ID_EMPRESA en este servidor
                    // solo debe existir si es de este entorno.
                    
                    define('ESTATUS', 'accedido');
                    if ($driver === 'pgsql') {
                        $port = getenv('DB_PORT') ?: '5432';
                        define('LOCAL_DNS', 'pgsql:host=' . $targetHost . ';port=' . $port . ';dbname=' . $connectionDetails['db_name']);
                    } else {
                        define('LOCAL_DNS', 'mysql:host=' . $targetHost . ';dbname=' . $connectionDetails['db_name']);
                    }
                    define('EMPRESA_NOMBRE', $connectionDetails['nombre']);
                    define('LOCAL_USER', $connectionDetails['db_user']);
                    define('LOCAL_PASS', $connectionDetails['db_password']);
                    define('LOCAL_DB', $connectionDetails['db_name']);
                } else {
                    define('ESTATUS', 'Cliente no existe');
                    define('LOCAL_DNS', ($driver === 'pgsql' ? 'pgsql' : 'mysql') . ':host=none;dbname=none');
                    define('EMPRESA_NOMBRE', 'None');
                    define('LOCAL_USER', 'none');
                    define('LOCAL_PASS', 'none');
                    define('LOCAL_DB', 'none');
                }
            } catch (\PDOException $e) {
                define('ESTATUS', 'error');
                define('LOCAL_DNS', ($driver === 'pgsql' ? 'pgsql' : 'mysql') . ':host=none;dbname=none');
                define('EMPRESA_NOMBRE', 'Error');
                define('LOCAL_USER', 'none');
                define('LOCAL_PASS', 'none');
                define('LOCAL_DB', 'none');
                error_log('Database connection failed: ' . $e->getMessage());
            }
        } else {
            define('ESTATUS', 'No Empresa');
            define('LOCAL_DNS', (getenv('DB_DRIVER') === 'pgsql' ? 'pgsql' : 'mysql') . ':host=none;dbname=none');
            define('EMPRESA_NOMBRE', 'None');
            define('LOCAL_USER', 'none');
            define('LOCAL_PASS', 'none');
            define('LOCAL_DB', 'none');
        }

        return $handler->handle($request);
    }

    private function getTimezoneByCountry(?string $country): string
    {
        if (!$country) {
            return 'America/Caracas';
        }

        $country = mb_strtolower(trim($country), 'UTF-8');

        $map = [
            'venezuela' => 'America/Caracas',
            'colombia' => 'America/Bogota',
            'ecuador' => 'America/Guayaquil',
            'peru' => 'America/Lima',
            'chile' => 'America/Santiago',
            'argentina' => 'America/Argentina/Buenos_Aires',
            'españa' => 'Europe/Madrid',
            'espana' => 'Europe/Madrid',
            'panama' => 'America/Panama',
            'mexico' => 'America/Mexico_City',
            'eeuu' => 'America/New_York',
            'usa' => 'America/New_York',
            'united states' => 'America/New_York',
        ];

        return $map[$country] ?? 'America/Caracas';
    }
}
