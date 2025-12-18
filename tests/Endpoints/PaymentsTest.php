<?php

namespace Tests\Endpoints;

use Tests\ApiTestCase;

/**
 * Pruebas para los endpoints de pagos
 * 
 * Endpoints probados:
 * - POST /pagos/pagar-a-empleados
 * - POST /pagos/terminar-planilla
 */
class PaymentsTest extends ApiTestCase
{
    /**
     * Test: Validación - IDs de pago vacíos deben retornar error
     */
    public function testPagarEmpleadosSinIdsPagoRetornaError(): void
    {
        $response = $this->postApi('/pagos/pagar-a-empleados', [
            'id_pagos' => '',
            'salario' => 0,
            'comision' => 0
        ]);

        $this->assertApiError($response, 400);
        $this->assertStringContainsString('IDs de pago', $response['message']);
    }

    /**
     * Test: Validación - IDs de pago inválidos (ceros) deben retornar error
     */
    public function testPagarEmpleadosConIdsInvalidosRetornaError(): void
    {
        $response = $this->postApi('/pagos/pagar-a-empleados', [
            'id_pagos' => '0,0,0',
            'salario' => 0,
            'comision' => 0
        ]);

        $this->assertApiError($response, 400);
    }

    /**
     * Test: Estructura de respuesta exitosa contiene campos esperados
     * 
     * NOTA: Este test requiere IDs de pago válidos en la base de datos de pruebas
     * Se puede comentar si no hay datos de prueba disponibles
     */
    public function testPagarEmpleadosEstructuraRespuestaExitosa(): void
    {
        $this->markTestSkipped(
            'Test requiere IDs de pago válidos. Descomentar y ajustar con datos reales.'
        );

        // Descomentar y ajustar con IDs de pago reales para pruebas manuales:
        // $response = $this->postApi('/pagos/pagar-a-empleados', [
        //     'id_pagos' => '123,124',
        //     'salario' => 100,
        //     'comision' => 50,
        //     'bonos' => json_encode([]),
        //     'descuentos' => json_encode([])
        // ]);
        // 
        // $this->assertApiSuccess($response);
        // $this->assertArrayHasKey('cantidad_pagos', $response['body']);
        // $this->assertArrayHasKey('monto_total_pagado', $response['body']);
    }

    /**
     * Test: Terminar planilla retorna respuesta exitosa
     * 
     * NOTA: Este test modifica datos en la base de datos
     * Solo ejecutar en ambiente de pruebas
     */
    public function testTerminarPlanillaRetornaExito(): void
    {
        $this->markTestSkipped(
            'Test modifica datos en BD. Ejecutar manualmente en ambiente de pruebas.'
        );

        // Descomentar para pruebas manuales:
        // $response = $this->postApi('/pagos/terminar-planilla', []);
        // 
        // $this->assertApiSuccess($response);
        // $this->assertArrayHasKey('fecha_pago', $response['body']);
        // $this->assertArrayHasKey('registros_actualizados', $response['body']);
    }

    /**
     * Test: Formato de respuesta ApiResponse para pagar empleados
     */
    public function testPagarEmpleadosFormatoApiResponse(): void
    {
        $response = $this->postApi('/pagos/pagar-a-empleados', [
            'id_pagos' => ''
        ]);

        // Verificar que la respuesta tiene el formato ApiResponse
        $this->assertArrayHasKey('success', $response['body']);
        $this->assertArrayHasKey('message', $response['body']);
        $this->assertIsBool($response['body']['success']);
        $this->assertIsString($response['body']['message']);
    }
}
