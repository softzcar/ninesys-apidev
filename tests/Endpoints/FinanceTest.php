<?php

namespace Tests\Endpoints;

use Tests\ApiTestCase;

/**
 * Pruebas para los endpoints de finanzas
 * 
 * Endpoints probados:
 * - POST /otro-abono
 */
class FinanceTest extends ApiTestCase
{
    /**
     * Test: Validación - Abono con monto cero debe retornar error
     */
    public function testOtroAbonoMontoZeroRetornaError(): void
    {
        $response = $this->postApi('/otro-abono', [
            'abono' => 0,
            'id_orden' => 1,
            'id_empleado' => 1,
            'tipoAbono' => 'abono',
            'detalle' => 'Test'
        ]);

        $this->assertApiError($response, 400);
        $this->assertStringContainsString('monto', strtolower($response['message']));
    }

    /**
     * Test: Validación - Abono con monto negativo debe retornar error
     */
    public function testOtroAbonoMontoNegativoRetornaError(): void
    {
        $response = $this->postApi('/otro-abono', [
            'abono' => -100,
            'id_orden' => 1,
            'id_empleado' => 1,
            'tipoAbono' => 'abono'
        ]);

        $this->assertApiError($response, 400);
    }

    /**
     * Test: Formato de respuesta ApiResponse para otro-abono
     */
    public function testOtroAbonoFormatoApiResponse(): void
    {
        $response = $this->postApi('/otro-abono', [
            'abono' => 0
        ]);

        // Verificar que la respuesta tiene el formato ApiResponse
        $this->assertArrayHasKey('success', $response['body']);
        $this->assertArrayHasKey('message', $response['body']);
        $this->assertIsBool($response['body']['success']);
        $this->assertIsString($response['body']['message']);
    }

    /**
     * Test: Abono exitoso con múltiples métodos de pago
     * 
     * NOTA: Este test modifica datos en la base de datos
     * Solo ejecutar en ambiente de pruebas
     */
    public function testOtroAbonoExitosoConMultiplesMetodos(): void
    {
        $this->markTestSkipped(
            'Test modifica datos en BD. Ejecutar manualmente en ambiente de pruebas.'
        );

        // Descomentar para pruebas manuales:
        // $response = $this->postApi('/otro-abono', [
        //     'abono' => 100,
        //     'id_orden' => 1,
        //     'id_empleado' => 1,
        //     'tipoAbono' => 'abono',
        //     'detalle' => 'Test automatizado',
        //     'montoDolaresEfectivo' => 50,
        //     'montoDolaresZelle' => 50,
        //     'montoDolaresPanama' => 0,
        //     'montoPesosEfectivo' => 0,
        //     'montoPesosTransferencia' => 0,
        //     'montoBolivaresEfectivo' => 0,
        //     'montoBolivaresPunto' => 0,
        //     'montoBolivaresPagomovil' => 0,
        //     'montoBolivaresTransferencia' => 0,
        //     'tasa_peso' => 1,
        //     'tasa_dolar' => 50
        // ]);
        // 
        // $this->assertApiSuccess($response);
        // $this->assertArrayHasKey('metodos_registrados', $response['body']);
        // $this->assertEquals(2, $response['body']['metodos_registrados']);
    }

    /**
     * Test: Respuesta exitosa incluye campos esperados
     */
    public function testOtroAbonoRespuestaIncluyeCamposEsperados(): void
    {
        $this->markTestSkipped(
            'Test requiere datos válidos. Ejecutar manualmente.'
        );

        // Los campos esperados en una respuesta exitosa son:
        // - id_orden
        // - monto_abono
        // - metodos_registrados
    }
}
