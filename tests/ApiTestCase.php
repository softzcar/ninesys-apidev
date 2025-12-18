<?php

namespace Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use GuzzleHttp\Client;

/**
 * Clase base para pruebas de API
 * Proporciona métodos de utilidad para hacer peticiones HTTP a los endpoints
 */
abstract class ApiTestCase extends BaseTestCase
{
    protected Client $client;
    protected string $baseUrl;
    protected int $idEmpresa = 152; // Empresa NineteenCustom

    protected function setUp(): void
    {
        parent::setUp();

        $this->baseUrl = getenv('API_BASE_URL') ?: 'https://apidev.nineteengreen.com';

        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => 30,
            'http_errors' => false, // No lanzar excepciones en errores HTTP
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept' => 'application/json'
            ]
        ]);
    }

    /**
     * Helper para hacer peticiones POST a la API
     */
    protected function postApi(string $endpoint, array $data = []): array
    {
        // Agregar id de empresa si no está presente
        if (!isset($data['id'])) {
            $data['id'] = $this->idEmpresa;
        }

        $response = $this->client->post($endpoint, [
            'form_params' => $data
        ]);

        $statusCode = $response->getStatusCode();
        $body = json_decode($response->getBody()->getContents(), true);

        return [
            'status_code' => $statusCode,
            'body' => $body,
            'success' => $body['success'] ?? false,
            'message' => $body['message'] ?? ''
        ];
    }

    /**
     * Helper para hacer peticiones GET a la API
     */
    protected function getApi(string $endpoint, array $query = []): array
    {
        // Agregar id de empresa si no está presente
        if (!isset($query['id'])) {
            $query['id'] = $this->idEmpresa;
        }

        $response = $this->client->get($endpoint, [
            'query' => $query
        ]);

        $statusCode = $response->getStatusCode();
        $body = json_decode($response->getBody()->getContents(), true);

        return [
            'status_code' => $statusCode,
            'body' => $body,
            'success' => $body['success'] ?? null,
            'message' => $body['message'] ?? ''
        ];
    }

    /**
     * Verifica que una respuesta sea exitosa según el formato ApiResponse
     */
    protected function assertApiSuccess(array $response, string $message = ''): void
    {
        $this->assertArrayHasKey('success', $response['body'], 'La respuesta debe contener el campo "success"');
        $this->assertTrue($response['body']['success'], $message ?: 'La respuesta debe indicar success: true');
        $this->assertEquals(200, $response['status_code'], 'El código HTTP debe ser 200');
    }

    /**
     * Verifica que una respuesta sea un error según el formato ApiResponse
     */
    protected function assertApiError(array $response, int $expectedStatusCode = 400): void
    {
        $this->assertEquals($expectedStatusCode, $response['status_code'], 'El código HTTP debe ser ' . $expectedStatusCode);
        $this->assertFalse($response['success'], 'La respuesta debe indicar success: false');
    }
}
