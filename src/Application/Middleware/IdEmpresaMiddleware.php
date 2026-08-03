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
        // Obtener el parámetro del encabezado `Authorization`
        $id_empresa = isset($request->getHeader('Authorization')[0]) ? (int) $request->getHeader('Authorization')[0] : null;

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
