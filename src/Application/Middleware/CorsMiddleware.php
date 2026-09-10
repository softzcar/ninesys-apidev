<?php
declare(strict_types=1);

namespace App\Application\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Routing\RouteContext;

class CorsMiddleware implements MiddlewareInterface
{
    // Orígenes reales que llaman a esta API desde el navegador -- confirmado
    // por auditoría de código 2026-09-10: app_multi (Prod y Dev) y el portal
    // setup_company (Prod y Dev). 19print/dtf NUNCA llama directo desde su
    // frontend (siempre pasa por su propio backend, servidor-a-servidor, que
    // no necesita CORS). localhost:3000 es el servidor local de desarrollo
    // usado para pruebas en navegador durante esta sesión.
    private const ALLOWED_ORIGINS = [
        'https://app.ninesys19.com',
        'https://app.nineteengreen.com',
        'https://setup.ninesys19.com',
        'https://setup.nineteengreen.com',
        'http://localhost:3000',
    ];

    public function process(Request $request, RequestHandler $handler): Response
    {
        // Manejar preflight OPTIONS
        if ($request->getMethod() === 'OPTIONS') {
            $response = new \Slim\Psr7\Response();
        } else {
            $response = $handler->handle($request);
        }

        // Antes era '*' (cualquier sitio en Internet podía hacer fetch()
        // cross-origin contra esta API desde el navegador de un visitante) --
        // auditoría de seguridad 2026-09-09/10. Se refleja el Origin de la
        // petición solo si está en la lista blanca; si no, se omite la
        // cabecera (curl/servidor-a-servidor no la necesita, el navegador es
        // quien la exige, y un origen no listado simplemente no la recibe).
        $origin = $request->getHeaderLine('Origin');
        $response = $response->withHeader('Vary', 'Origin');
        if (in_array($origin, self::ALLOWED_ORIGINS, true)) {
            $response = $response->withHeader('Access-Control-Allow-Origin', $origin);
        }

        $response = $response
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');

        // Cabeceras de seguridad -- Cloudflare Free no permite Transform Rules de
        // respuesta (Rulesets fase http_response_headers_transform, plan de pago),
        // así que se agregan acá directo en el origen (auditoría de seguridad
        // 2026-09-09). Independientes del plan de Cloudflare.
        $response = $response
            ->withHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains')
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('X-Frame-Options', 'DENY')
            ->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        return $response;
    }
}
