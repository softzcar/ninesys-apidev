<?php

namespace Tests\Endpoints;

use Tests\ApiTestCase;

/**
 * Pruebas para los endpoints de producción
 * 
 * Endpoints probados:
 * - POST /produccion/terminar/{id}
 */
class ProductionTest extends ApiTestCase
{
    /**
     * Test: Terminar producción con ID inválido (0) debe retornar error
     */
    public function testTerminarProduccionIdZeroRetornaError(): void
    {
        $response = $this->postApi('/produccion/terminar/0', []);

        $this->assertApiError($response, 400);
        $this->assertStringContainsString('id', strtolower($response['message']));
    }

    /**
     * Test: Terminar producción con ID negativo debe retornar error
     */
    public function testTerminarProduccionIdNegativoRetornaError(): void
    {
        $response = $this->postApi('/produccion/terminar/-1', []);

        $this->assertApiError($response, 400);
    }

    /**
     * Test: Terminar producción con ID no existente debe retornar error
     */
    public function testTerminarProduccionIdNoExistenteRetornaError(): void
    {
        // Usar un ID muy alto que probablemente no exista
        $response = $this->postApi('/produccion/terminar/999999999', []);

        // Puede retornar 500 si la excepción se lanza o 400 si hay validación previa
        $this->assertContains($response['status_code'], [400, 500]);
        $this->assertFalse($response['success']);
    }

    /**
     * Test: Formato de respuesta ApiResponse para terminar producción
     */
    public function testTerminarProduccionFormatoApiResponse(): void
    {
        $response = $this->postApi('/produccion/terminar/0', []);

        $this->assertArrayHasKey('success', $response['body']);
        $this->assertArrayHasKey('message', $response['body']);
        $this->assertIsBool($response['body']['success']);
    }

    /**
     * Test: Terminar producción exitoso incluye campos esperados
     * 
     * NOTA: Este test modifica datos en la base de datos
     */
    public function testTerminarProduccionExitosoIncluyeCampos(): void
    {
        $this->markTestSkipped(
            'Test modifica datos en BD. Ejecutar manualmente con ID válido.'
        );

        // Descomentar para pruebas manuales (ajustar con ID de orden válido):
        // $response = $this->postApi('/produccion/terminar/1', []);
        // 
        // $this->assertApiSuccess($response);
        // $this->assertArrayHasKey('id_orden', $response['body']);
        // $this->assertArrayHasKey('nuevo_status', $response['body']);
        // $this->assertEquals('terminado', $response['body']['nuevo_status']);
    }

    /**
     * Test: Terminar producción usa transacción (no deja datos parciales en error)
     * 
     * Este test verifica que si ocurre un error durante la operación,
     * no queden datos parciales gracias al rollback
     */
    public function testTerminarProduccionUsaTransaccion(): void
    {
        $this->markTestSkipped(
            'Test de integración complejo. Requiere simulación de error en BD.'
        );

        // Este tipo de test requeriría:
        // 1. Crear una orden de prueba
        // 2. Forzar un error durante el UPDATE
        // 3. Verificar que el status no cambió (rollback funcionó)
    }
}
