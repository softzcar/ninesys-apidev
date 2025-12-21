<?php

require_once __DIR__ . '/BaseQuery.php';

/**
 * PagosQueries - Consultas relacionadas con pagos y comisiones
 * 
 * Contiene consultas SQL para:
 * - Comisiones de empleados
 * - Pagos de órdenes
 * - Reportes de caja
 * - Abonos y saldos
 * 
 * @package NineSys\Queries
 */
class PagosQueries extends BaseQuery
{
    /**
     * Comisiones pendientes de un empleado
     * 
     * @ai-description Calcula el total de comisiones pendientes de pago para un empleado
     * @ai-keywords comision, pago, empleado, pendiente, deuda, deber
     * @ai-ejemplo ¿Cuánto le debo a Juan? | Comisiones pendientes de María | Pago de empleado
     * 
     * @param int $idEmpleado ID del empleado
     * @param string|null $fechaInicio Fecha inicio del período
     * @param string|null $fechaFin Fecha fin del período
     * @return string SQL query
     */
    public static function comisionesPendientesEmpleado(int $idEmpleado, ?string $fechaInicio = null, ?string $fechaFin = null): string
    {
        $whereFecha = self::rangoFechas('ld.fecha_terminado', $fechaInicio, $fechaFin);

        return "
            SELECT 
                SUM(ld.comision) as total_comision,
                COUNT(*) as tareas_completadas,
                GROUP_CONCAT(DISTINCT ld.departamento) as departamentos
            FROM lotes_detalles ld
            WHERE ld.id_empleado = {$idEmpleado}
            AND ld.terminado = 1
            AND (ld.pago_comision IS NULL OR ld.pago_comision = 'pendiente')
            {$whereFecha}
        ";
    }

    /**
     * Detalle de comisiones por tarea de un empleado
     * 
     * @ai-description Lista el detalle de cada tarea que genera comisión para un empleado
     * @ai-keywords comision, detalle, tareas, empleado, desglose
     * @ai-ejemplo Detalle de comisiones de Juan | Desglose de pagos
     * 
     * @param int $idEmpleado ID del empleado
     * @param string|null $fechaInicio Fecha inicio
     * @param string|null $fechaFin Fecha fin
     * @return string SQL query
     */
    public static function detalleComisionesEmpleado(int $idEmpleado, ?string $fechaInicio = null, ?string $fechaFin = null): string
    {
        $whereFecha = self::rangoFechas('ld.fecha_terminado', $fechaInicio, $fechaFin);

        return "
            SELECT 
                ld._id,
                ld.id_orden,
                o.cliente_nombre,
                op.name as producto,
                ld.departamento,
                ld.unidades_solicitadas,
                ld.comision,
                ld.fecha_terminado,
                ld.pago_comision
            FROM lotes_detalles ld
            INNER JOIN ordenes o ON ld.id_orden = o._id
            INNER JOIN ordenes_productos op ON ld.id_ordenes_productos = op._id
            WHERE ld.id_empleado = {$idEmpleado}
            AND ld.terminado = 1
            {$whereFecha}
            ORDER BY ld.fecha_terminado DESC
        ";
    }

    /**
     * Resumen de caja por período
     * 
     * @ai-description Calcula ingresos y egresos de caja en un período
     * @ai-keywords caja, ingresos, egresos, dinero, efectivo, balance
     * @ai-ejemplo Cuánto ingresó hoy a caja | Balance de caja del mes | Movimientos de caja
     * 
     * @param string|null $fechaInicio Fecha inicio
     * @param string|null $fechaFin Fecha fin
     * @return string SQL query
     */
    public static function resumenCaja(?string $fechaInicio = null, ?string $fechaFin = null): string
    {
        $whereFecha = self::rangoFechas('moment', $fechaInicio, $fechaFin);

        return "
            SELECT 
                SUM(CASE WHEN tipo = 'ingreso' THEN monto ELSE 0 END) as total_ingresos,
                SUM(CASE WHEN tipo = 'egreso' THEN monto ELSE 0 END) as total_egresos,
                SUM(CASE WHEN tipo = 'ingreso' THEN monto ELSE -monto END) as balance,
                COUNT(*) as total_movimientos
            FROM caja
            WHERE 1=1 {$whereFecha}
        ";
    }

    /**
     * Pagos recibidos de un cliente
     * 
     * @ai-description Lista todos los pagos/abonos recibidos de un cliente
     * @ai-keywords pago, cliente, abono, historial, cobro
     * @ai-ejemplo Pagos de cliente Juan | Historial de abonos | Cuánto ha pagado
     * 
     * @param string $nombreCliente Nombre del cliente
     * @return string SQL query
     */
    public static function pagosPorCliente(string $nombreCliente): string
    {
        $where = self::likeClause('o.cliente_nombre', $nombreCliente);

        return "
            SELECT 
                p._id,
                p.id_orden,
                o.cliente_nombre,
                p.monto,
                p.metodo,
                p.descripcion,
                p.moment as fecha_pago
            FROM pagos p
            INNER JOIN ordenes o ON p.id_orden = o._id
            WHERE {$where}
            ORDER BY p.moment DESC
        ";
    }

    /**
     * Resumen de métodos de pago
     * 
     * @ai-description Muestra cuánto se ha cobrado por cada método de pago
     * @ai-keywords metodo, efectivo, transferencia, punto, tarjeta, zelle
     * @ai-ejemplo Cuánto en efectivo | Pagos por transferencia | Resumen por método de pago
     * 
     * @param string|null $fechaInicio Fecha inicio
     * @param string|null $fechaFin Fecha fin
     * @return string SQL query
     */
    public static function resumenMetodosPago(?string $fechaInicio = null, ?string $fechaFin = null): string
    {
        $whereFecha = self::rangoFechas('moment', $fechaInicio, $fechaFin);

        return "
            SELECT 
                metodo,
                SUM(monto) as total,
                COUNT(*) as cantidad_pagos
            FROM pagos
            WHERE 1=1 {$whereFecha}
            GROUP BY metodo
            ORDER BY total DESC
        ";
    }

    /**
     * Empleados con comisiones pendientes
     * 
     * @ai-description Lista todos los empleados que tienen comisiones sin pagar
     * @ai-keywords empleados, comisiones, pendientes, todos, resumen
     * @ai-ejemplo A quiénes les debo | Empleados con comisiones pendientes
     * 
     * @return string SQL query
     */
    public static function empleadosConComisionesPendientes(): string
    {
        return "
            SELECT 
                eu.id_usuario,
                eu.nombre as empleado,
                eu.departamento,
                SUM(ld.comision) as total_pendiente,
                COUNT(*) as tareas_pendientes
            FROM lotes_detalles ld
            INNER JOIN api_empresas.empresas_usuarios eu ON ld.id_empleado = eu.id_usuario
            WHERE ld.terminado = 1
            AND (ld.pago_comision IS NULL OR ld.pago_comision = 'pendiente')
            GROUP BY eu.id_usuario, eu.nombre, eu.departamento
            HAVING total_pendiente > 0
            ORDER BY total_pendiente DESC
        ";
    }
}
