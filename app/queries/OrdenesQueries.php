<?php

require_once __DIR__ . '/BaseQuery.php';

/**
 * OrdenesQueries - Consultas relacionadas con órdenes de producción
 * 
 * Contiene consultas SQL para:
 * - Búsqueda de órdenes
 * - Estadísticas de órdenes
 * - Productos de órdenes
 * - Estados y seguimiento
 * 
 * @package NineSys\Queries
 */
class OrdenesQueries extends BaseQuery
{
    /**
     * Buscar órdenes por nombre de cliente
     * 
     * @ai-description Busca órdenes filtrando por nombre del cliente
     * @ai-keywords orden, cliente, buscar, nombre
     * @ai-ejemplo Órdenes del cliente Juan | Buscar orden de María
     * 
     * @param string $nombreCliente Nombre o parte del nombre del cliente
     * @param int|null $limit Límite de resultados
     * @return string SQL query
     */
    public static function buscarPorCliente(string $nombreCliente, ?int $limit = 50): string
    {
        $where = self::likeClause('cliente_nombre', $nombreCliente);
        $limitClause = $limit ? "LIMIT {$limit}" : '';

        return "
            SELECT 
                _id,
                cliente_nombre,
                cliente_cedula,
                status,
                fecha_inicio,
                fecha_entrega,
                pago_total,
                pago_abono,
                (pago_total - pago_abono) as saldo_pendiente,
                moment
            FROM ordenes
            WHERE {$where}
            ORDER BY moment DESC
            {$limitClause}
        ";
    }

    /**
     * Obtener resumen de ventas por período
     * 
     * @ai-description Calcula el total de ventas en un período de tiempo
     * @ai-keywords ventas, total, mes, semana, período, ingresos
     * @ai-ejemplo Ventas del mes | Total de ventas de diciembre | Cuánto vendimos ayer
     * 
     * @param string|null $fechaInicio Fecha inicio (Y-m-d)
     * @param string|null $fechaFin Fecha fin (Y-m-d)
     * @return string SQL query
     */
    public static function resumenVentas(?string $fechaInicio = null, ?string $fechaFin = null): string
    {
        $whereFecha = self::rangoFechas('moment', $fechaInicio, $fechaFin);

        return "
            SELECT 
                COUNT(*) as total_ordenes,
                SUM(pago_total) as total_ventas,
                SUM(pago_abono) as total_cobrado,
                SUM(pago_total - pago_abono) as total_pendiente,
                AVG(pago_total) as promedio_orden
            FROM ordenes
            WHERE status NOT IN ('cancelada')
            {$whereFecha}
        ";
    }

    /**
     * Órdenes pendientes de entrega
     * 
     * @ai-description Lista órdenes que están listas pero no se han entregado
     * @ai-keywords pendiente, entrega, entregar, terminada, lista
     * @ai-ejemplo Órdenes listas para entregar | Pendientes de entrega | Qué órdenes puedo entregar
     * 
     * @param int|null $limit Límite de resultados
     * @return string SQL query
     */
    public static function pendientesEntrega(?int $limit = 100): string
    {
        $limitClause = $limit ? "LIMIT {$limit}" : '';

        return "
            SELECT 
                o._id,
                o.cliente_nombre,
                o.cliente_cedula,
                o.fecha_entrega,
                o.pago_total,
                o.pago_abono,
                (o.pago_total - o.pago_abono) as saldo_pendiente
            FROM ordenes o
            WHERE o.status = 'terminada'
            ORDER BY o.fecha_entrega ASC
            {$limitClause}
        ";
    }

    /**
     * Productos de una orden específica
     * 
     * @ai-description Obtiene todos los productos de una orden
     * @ai-keywords productos, orden, detalle, items
     * @ai-ejemplo Productos de la orden 1234 | Qué incluye la orden
     * 
     * @param int $idOrden ID de la orden
     * @return string SQL query
     */
    public static function productosDeOrden(int $idOrden): string
    {
        return "
            SELECT 
                op._id,
                op.name as producto,
                op.cantidad,
                op.talla,
                op.tela,
                op.precio_unitario,
                (op.cantidad * op.precio_unitario) as subtotal,
                op.corte
            FROM ordenes_productos op
            WHERE op.id_orden = {$idOrden}
            ORDER BY op._id
        ";
    }

    /**
     * Órdenes con saldo pendiente
     * 
     * @ai-description Lista órdenes que tienen saldo pendiente de cobro
     * @ai-keywords deuda, pendiente, cobrar, saldo, debe
     * @ai-ejemplo Clientes que deben | Órdenes sin pagar | Cuánto nos deben
     * 
     * @param float|null $montoMinimo Monto mínimo de deuda
     * @param int|null $limit Límite de resultados
     * @return string SQL query
     */
    public static function conSaldoPendiente(?float $montoMinimo = 0, ?int $limit = 100): string
    {
        $limitClause = $limit ? "LIMIT {$limit}" : '';

        return "
            SELECT 
                _id,
                cliente_nombre,
                cliente_cedula,
                status,
                pago_total,
                pago_abono,
                (pago_total - pago_abono) as saldo_pendiente,
                fecha_entrega
            FROM ordenes
            WHERE (pago_total - pago_abono) > {$montoMinimo}
            AND status NOT IN ('cancelada')
            ORDER BY (pago_total - pago_abono) DESC
            {$limitClause}
        ";
    }
}
