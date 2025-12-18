<?php

use Psr\Http\Message\ResponseInterface;

/**
 * ApiResponse - Clase helper para respuestas estandarizadas de la API
 * 
 * Proporciona métodos estáticos para generar respuestas JSON consistentes
 * que incluyen campos 'success' y 'message' para el manejo de errores en el frontend.
 */
class ApiResponse
{
    /**
     * Genera una respuesta exitosa
     * 
     * @param Response $response Objeto de respuesta de Slim
     * @param string $message Mensaje descriptivo para el usuario
     * @param array $data Datos adicionales a incluir en la respuesta
     * @param int $statusCode Código HTTP (default: 200)
     * @return Response
     */
    public static function success($response, $message, $data = [], $statusCode = 200)
    {
        $result = [
            'success' => true,
            'message' => $message
        ];

        // Agregar datos adicionales si existen
        if (!empty($data)) {
            $result = array_merge($result, $data);
        }

        $response->getBody()->write(json_encode($result));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($statusCode);
    }

    /**
     * Genera una respuesta de error
     * 
     * @param Response $response Objeto de respuesta de Slim
     * @param string $message Mensaje de error para el usuario
     * @param int $statusCode Código HTTP (default: 500)
     * @param array $data Datos adicionales a incluir en la respuesta
     * @return Response
     */
    public static function error($response, $message, $statusCode = 500, $data = [])
    {
        $result = [
            'success' => false,
            'message' => $message
        ];

        if (!empty($data)) {
            $result = array_merge($result, $data);
        }

        $response->getBody()->write(json_encode($result));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($statusCode);
    }

    /**
     * Error de validación (HTTP 400)
     * 
     * @param Response $response Objeto de respuesta de Slim
     * @param string $message Mensaje de error de validación
     * @return Response
     */
    public static function validationError($response, $message)
    {
        return self::error($response, $message, 400);
    }

    /**
     * Recurso no encontrado (HTTP 404)
     * 
     * @param Response $response Objeto de respuesta de Slim
     * @param string $message Mensaje de error (default: 'Recurso no encontrado')
     * @return Response
     */
    public static function notFound($response, $message = 'Recurso no encontrado')
    {
        return self::error($response, $message, 404);
    }

    /**
     * Error de servidor (HTTP 500)
     * 
     * @param Response $response Objeto de respuesta de Slim
     * @param string $message Mensaje de error
     * @param Exception|null $e Excepción para logging (opcional)
     * @return Response
     */
    public static function serverError($response, $message, $e = null)
    {
        if ($e !== null) {
            error_log('API Server Error: ' . $e->getMessage());
        }
        return self::error($response, $message, 500);
    }
}
