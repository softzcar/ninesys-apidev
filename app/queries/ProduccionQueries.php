<?php

require_once __DIR__ . '/BaseQuery.php';

/**
 * ProduccionQueries - Consultas relacionadas con producción y manufactura
 * 
 * Contiene consultas SQL para:
 * - Estado de lotes y tareas
 * - Asignaciones de empleados
 * - Tiempos de producción
 * - Seguimiento de órdenes en fabricación
 * 
 * @package NineSys\Queries
 */
class ProduccionQueries extends BaseQuery
{
    /**
     * Tareas pendientes de un empleado
     * 
     * @ai-description Obtiene las tareas asignadas a un empleado que no han terminado
     * @ai-keywords tareas, pendiente, empleado, trabajo, asignado
     * @ai-ejemplo Tareas de Juan | Qué tiene pendiente María | Trabajo asignado a Pedro
     * 
     * @param int $idEmpleado ID del empleado
     * @return string SQL query
     */
    public static function tareasPendientesEmpleado(int $idEmpleado): string
    {
        return "
            SELECT 
                ld._id,
                ld.id_orden,
                o.cliente_nombre,
                op.name as producto,
                ld.unidades_solicitadas,
                ld.departamento,
                ld.progreso,
                ld.fecha_inicio,
                ld.comision
            FROM lotes_detalles ld
            INNER JOIN ordenes o ON ld.id_orden = o._id
            INNER JOIN ordenes_productos op ON ld.id_ordenes_productos = op._id
            WHERE ld.id_empleado = {$idEmpleado}
            AND ld.terminado = 0
            ORDER BY ld.fecha_inicio ASC
        ";
    }

    /**
     * Estado actual de una orden en producción
     * 
     * @ai-description Muestra en qué paso del proceso está una orden
     * @ai-keywords estado, orden, paso, proceso, dónde está, producción
     * @ai-ejemplo ¿En qué va la orden 1234? | Estado de producción | Dónde está la orden
     * 
     * @param int $idOrden ID de la orden
     * @return string SQL query
     */
    public static function estadoOrden(int $idOrden): string
    {
        return "
            SELECT 
                l._id as id_lote,
                l.lote as codigo_lote,
                l.paso as paso_actual,
                l.prioridad,
                l.piezas_actuales,
                d.departamento as departamento_actual,
                d.orden_proceso
            FROM lotes l
            LEFT JOIN departamentos d ON l.id_departamento_actual = d._id
            WHERE l.id_orden = {$idOrden}
        ";
    }

    /**
     * Órdenes en un departamento específico
     * 
     * @ai-description Lista las órdenes que están actualmente en un departamento
     * @ai-keywords departamento, órdenes, corte, costura, impresión, estampado
     * @ai-ejemplo Órdenes en costura | Qué hay en corte | Trabajo en impresión
     * 
     * @param int $idDepartamento ID del departamento
     * @param int|null $limit Límite de resultados
     * @return string SQL query
     */
    public static function ordenesPorDepartamento(int $idDepartamento, ?int $limit = 50): string
    {
        $limitClause = $limit ? "LIMIT {$limit}" : '';

        return "
            SELECT 
                l.id_orden,
                o.cliente_nombre,
                l.lote,
                l.paso,
                l.prioridad,
                l.piezas_actuales,
                o.fecha_entrega
            FROM lotes l
            INNER JOIN ordenes o ON l.id_orden = o._id
            WHERE l.id_departamento_actual = {$idDepartamento}
            ORDER BY l.prioridad DESC, o.fecha_entrega ASC
            {$limitClause}
        ";
    }

    /**
     * Tiempo trabajado por empleado en un período
     * 
     * @ai-description Calcula las horas trabajadas por un empleado
     * @ai-keywords tiempo, horas, trabajado, empleado, producción
     * @ai-ejemplo Horas de Juan esta semana | Tiempo trabajado por María
     * 
     * @param int $idEmpleado ID del empleado
     * @param string|null $fechaInicio Fecha inicio
     * @param string|null $fechaFin Fecha fin
     * @return string SQL query
     */
    public static function tiempoTrabajadoEmpleado(int $idEmpleado, ?string $fechaInicio = null, ?string $fechaFin = null): string
    {
        $whereFecha = self::rangoFechas('ldea.fecha_inicio', $fechaInicio, $fechaFin);

        return "
            SELECT 
                SUM(ldea.tiempo_total) as tiempo_segundos,
                SEC_TO_TIME(SUM(ldea.tiempo_total)) as tiempo_formato,
                COUNT(DISTINCT ldea.id_orden) as ordenes_trabajadas,
                COUNT(*) as tareas_completadas
            FROM lotes_detalles_empleados_asignados ldea
            WHERE ldea.id_empleado = {$idEmpleado}
            AND ldea.fecha_fin IS NOT NULL
            {$whereFecha}
        ";
    }

    /**
     * Resumen de producción por departamento
     * 
     * @ai-description Muestra cuántas órdenes hay en cada departamento
     * @ai-keywords resumen, producción, departamentos, carga, trabajo
     * @ai-ejemplo Carga de trabajo por departamento | Resumen de producción
     * 
     * @return string SQL query
     */
    public static function resumenPorDepartamento(): string
    {
        return "
            SELECT 
                d.departamento,
                d.orden_proceso,
                COUNT(l.id_orden) as ordenes_activas,
                SUM(l.piezas_actuales) as piezas_total,
                SUM(CASE WHEN l.prioridad = 1 THEN 1 ELSE 0 END) as ordenes_urgentes
            FROM departamentos d
            LEFT JOIN lotes l ON d._id = l.id_departamento_actual
            WHERE d.asignar_numero_de_paso = 1
            GROUP BY d._id, d.departamento, d.orden_proceso
            ORDER BY d.orden_proceso
        ";
    }

    /**
     * Órdenes urgentes en producción
     * 
     * @ai-description Lista todas las órdenes marcadas como urgentes
     * @ai-keywords urgente, prioridad, rush, rápido
     * @ai-ejemplo Órdenes urgentes | Pedidos con prioridad
     * 
     * @return string SQL query
     */
    public static function ordenesUrgentes(): string
    {
        return "
            SELECT 
                l.id_orden,
                o.cliente_nombre,
                l.paso as paso_actual,
                d.departamento,
                o.fecha_entrega,
                l.piezas_actuales
            FROM lotes l
            INNER JOIN ordenes o ON l.id_orden = o._id
            LEFT JOIN departamentos d ON l.id_departamento_actual = d._id
            WHERE l.prioridad = 1
            AND o.status NOT IN ('entregada', 'cancelada', 'terminada')
            ORDER BY o.fecha_entrega ASC
        ";
    }
}
