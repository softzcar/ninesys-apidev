<?php
declare(strict_types=1);

namespace App\Application\ResponseEmitter;

use Psr\Http\Message\ResponseInterface;
use Slim\ResponseEmitter as SlimResponseEmitter;

class ResponseEmitter extends SlimResponseEmitter
{
    // Mismo criterio y misma lista que CorsMiddleware.php -- este emisor corre
    // AL FINAL de todo (es quien realmente manda los headers al cliente) y
    // antes SOBRESCRIBÍA lo que CorsMiddleware ya había fijado, reflejando
    // literalmente CUALQUIER Origin sin validar y agregando
    // Access-Control-Allow-Credentials: true -- combinado, peor que un simple
    // '*' (permite peticiones cross-origin con credenciales desde cualquier
    // sitio). Auditoría de seguridad 2026-09-09/10.
    private const ALLOWED_ORIGINS = [
        'https://app.ninesys19.com',
        'https://app.nineteengreen.com',
        'https://setup.ninesys19.com',
        'https://setup.nineteengreen.com',
        'http://localhost:3000',
    ];

    /**
     * {@inheritdoc}
     */
    public function emit(ResponseInterface $response): void
    {
        $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';

        $response = $response
            ->withHeader('Vary', 'Origin')
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
            ->withHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->withAddedHeader('Cache-Control', 'post-check=0, pre-check=0')
            ->withHeader('Pragma', 'no-cache');

        // Esta API no usa cookies/sesión de navegador (el "login" es un
        // header propio, no una cookie) -- no hay ninguna razón real para
        // Access-Control-Allow-Credentials, y activarlo junto a un origen
        // reflejado es la combinación más peligrosa de CORS que existe.
        if (in_array($origin, self::ALLOWED_ORIGINS, true)) {
            $response = $response->withHeader('Access-Control-Allow-Origin', $origin);
        } else {
            $response = $response->withoutHeader('Access-Control-Allow-Origin');
        }

        if (ob_get_contents()) {
            ob_clean();
        }

        parent::emit($response);
    }
}
