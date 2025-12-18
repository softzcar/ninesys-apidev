<?php

namespace Tests\Endpoints;

use Tests\ApiTestCase;

/**
 * Pruebas para los endpoints de órdenes
 * 
 * Endpoints probados:
 * - POST /ordenes/nueva/custom
 * - POST /orden/abono
 */
class OrdersTest extends ApiTestCase
{
    /**
     * Test: Crear orden sin productos debe retornar error
     */
    public function testCrearOrdenSinProductosRetornaError(): void
    {
        $response = $this->postApi('/ordenes/nueva/custom', [
            'productos' => '',
            'responsable' => 1,
            'fechaEntrega' => date('Y-m-d', strtotime('+7 days'))
        ]);

        $this->assertApiError($response, 400);
        $this->assertStringContainsString('productos', strtolower($response['message']));
    }

    /**
     * Test: Crear orden sin responsable debe retornar error
     */
    public function testCrearOrdenSinResponsableRetornaError(): void
    {
        $response = $this->postApi('/ordenes/nueva/custom', [
            'productos' => json_encode([['id' => 1, 'cantidad' => 1]]),
            'responsable' => '',
            'fechaEntrega' => date('Y-m-d', strtotime('+7 days'))
        ]);

        $this->assertApiError($response, 400);
        $this->assertStringContainsString('responsable', strtolower($response['message']));
    }

    /**
     * Test: Crear orden sin fecha de entrega debe retornar error
     */
    public function testCrearOrdenSinFechaEntregaRetornaError(): void
    {
        $response = $this->postApi('/ordenes/nueva/custom', [
            'productos' => json_encode([['id' => 1, 'cantidad' => 1]]),
            'responsable' => 1,
            'fechaEntrega' => ''
        ]);

        $this->assertApiError($response, 400);
        $this->assertStringContainsString('fecha', strtolower($response['message']));
    }

    /**
     * Test: Formato de respuesta ApiResponse para crear orden
     */
    public function testCrearOrdenFormatoApiResponse(): void
    {
        $response = $this->postApi('/ordenes/nueva/custom', [
            'productos' => ''
        ]);

        // Verificar que la respuesta tiene el formato ApiResponse
        $this->assertArrayHasKey('success', $response['body']);
        $this->assertArrayHasKey('message', $response['body']);
    }

    /**
     * Test: Respuesta exitosa de orden incluye ID de orden
     * 
     * NOTA: Este test crea una orden real en la base de datos
     */
    public function testCrearOrdenExitosaIncluyeIdOrden(): void
    {
        $this->markTestSkipped(
            'Test crea datos en BD. Ejecutar manualmente en ambiente de pruebas.'
        );

        // Descomentar para pruebas manuales (ajustar con productos válidos):
        // $response = $this->postApi('/ordenes/nueva/custom', [
        //     'productos' => json_encode([
        //         ['idProduct' => 1, 'cantidad' => 2, 'price' => 100]
        //     ]),
        //     'responsable' => 1,
        //     'fechaEntrega' => date('Y-m-d', strtotime('+7 days')),
        //     'cliente' => 'Cliente Test',
        //     'cedula' => '12345678',
        //     'abono' => 50,
        //     'descuento' => 0
        // ]);
        // 
        // $this->assertApiSuccess($response);
        // $this->assertArrayHasKey('id_orden', $response['body']);
        // $this->assertIsInt($response['body']['id_orden']);
    }

    /**
     * Test: Abono sin ID de orden válido debe retornar error
     */
    public function testAbonoSinIdOrdenRetornaError(): void
    {
        $response = $this->postApi('/orden/abono', [
            'id_orden' => '',
            'abono' => 100
        ]);

        $this->assertApiError($response, 400);
    }

    /**
     * Test: Abono con monto cero no debe modificar datos
     */
    public function testAbonoMontoZeroNoModificaDatos(): void
    {
        $this->markTestSkipped(
            'Test requiere orden existente. Ejecutar manualmente.'
        );
    }

    /**
     * Test: Formato de respuesta ApiResponse para abono
     */
    public function testAbonoFormatoApiResponse(): void
    {
        $response = $this->postApi('/orden/abono', [
            'id_orden' => ''
        ]);

        $this->assertArrayHasKey('success', $response['body']);
        $this->assertArrayHasKey('message', $response['body']);
    }
}
