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
            $dsn = 'mysql:host=localhost;dbname=api_empresas';  // Ajusta estos datos si es necesario
            $user = 'api_adminemp';
            $password = 'rkyaFy!dAs8L5Lq8';

            try {
                $pdo = new PDO($dsn, $user, $password, [
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET lc_time_names = 'es_ES', NAMES utf8"
                ]);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                $sql = 'SELECT db_host, db_user, db_password, db_name FROM empresas WHERE id_empresa = :id_empresa';
                $stmt = $pdo->prepare($sql);
                $stmt->execute(['id_empresa' => $id_empresa]);

                $connectionDetails = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($connectionDetails) {
                    define('ESTATUS', 'accedido');
                    define('LOCAL_DNS', 'mysql:host=' . $connectionDetails['db_host'] . ';dbname=' . $connectionDetails['db_name']);
                    define('LOCAL_USER', $connectionDetails['db_user']);
                    define('LOCAL_PASS', $connectionDetails['db_password']);
                    define('LOCAL_DB', $connectionDetails['db_name']);
                } else {
                    define('ESTATUS', 'Cliente no existe');
                    define('LOCAL_DNS', 'mysql:host=none;dbname=none');
                    define('LOCAL_USER', 'none');
                    define('LOCAL_PASS', 'none');
                    define('LOCAL_DB', 'none');
                }
            } catch (PDOException $e) {
                define('ESTATUS', 'error');
                define('LOCAL_DNS', 'mysql:host=none;dbname=none');
                define('LOCAL_USER', 'none');
                define('LOCAL_PASS', 'none');
                define('LOCAL_DB', 'none');
                error_log('Database connection failed: ' . $e->getMessage());
            }
        }

        return $handler->handle($request);
    }
}
