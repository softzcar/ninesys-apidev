<?php

require_once __DIR__ . '/BaseQuery.php';

/**
 * InventarioQueries - Consultas relacionadas con inventario y materiales
 * 
 * Contiene consultas SQL para:
 * - Stock de materiales
 * - Movimientos de inventario
 * - Uso de materiales por orden
 * - Alertas de stock bajo
 * 
 * @package NineSys\Queries
 */
class InventarioQueries extends BaseQuery
{
    /**
     * Stock actual de todos los insumos
     * 
     * @ai-description Lista el inventario actual de todos los materiales
     * @ai-keywords inventario, stock, materiales, insumos, telas
     * @ai-ejemplo Inventario actual | Stock de materiales | Qué tenemos en inventario
     * 
     * @param int|null $limit Límite de resultados
     * @return string SQL query
     */
    public static function stockActual(?int $limit = 100): string
    {
        $limitClause = $limit ? "LIMIT {$limit}" : '';

        return "
            SELECT 
                i._id,
                i.insumo,
                i.cantidad,
                i.unidad,
                i.color,
                cip.nombre as categoria,
                i.moment as ultima_actualizacion
            FROM inventario i
            LEFT JOIN catalogo_insumos_productos cip ON i.id_catalogo = cip._id
            ORDER BY i.insumo
            {$limitClause}
        ";
    }

    /**
     * Insumos con stock bajo
     * 
     * @ai-description Lista materiales que están por debajo del mínimo
     * @ai-keywords bajo, minimo, alerta, agotado, poco, escaso
     * @ai-ejemplo Materiales con stock bajo | Qué se está agotando | Alertas de inventario
     * 
     * @param float $cantidadMinima Cantidad mínima para considerar stock bajo
     * @return string SQL query
     */
    public static function stockBajo(float $cantidadMinima = 10): string
    {
        return "
            SELECT 
                i._id,
                i.insumo,
                i.cantidad,
                i.unidad,
                i.color,
                cip.nombre as categoria
            FROM inventario i
            LEFT JOIN catalogo_insumos_productos cip ON i.id_catalogo = cip._id
            WHERE i.cantidad < {$cantidadMinima}
            AND i.cantidad >= 0
            ORDER BY i.cantidad ASC
        ";
    }

    /**
     * Movimientos de inventario por período
     * 
     * @ai-description Lista entradas y salidas de inventario
     * @ai-keywords movimientos, entradas, salidas, historial, inventario
     * @ai-ejemplo Movimientos de inventario de hoy | Entradas y salidas del mes
     * 
     * @param string|null $fechaInicio Fecha inicio
     * @param string|null $fechaFin Fecha fin
     * @param int|null $limit Límite de resultados
     * @return string SQL query
     */
    public static function movimientos(?string $fechaInicio = null, ?string $fechaFin = null, ?int $limit = 100): string
    {
        $whereFecha = self::rangoFechas('im.moment', $fechaInicio, $fechaFin);
        $limitClause = $limit ? "LIMIT {$limit}" : '';

        return "
            SELECT 
                im._id,
                i.insumo,
                im.tipo,
                im.cantidad,
                i.unidad,
                im.motivo,
                im.id_orden,
                im.moment as fecha
            FROM inventario_movimientos im
            INNER JOIN inventario i ON im.id_insumo = i._id
            WHERE 1=1 {$whereFecha}
            ORDER BY im.moment DESC
            {$limitClause}
        ";
    }

    /**
     * Uso de materiales por orden
     * 
     * @ai-description Muestra qué materiales se usaron en una orden específica
     * @ai-keywords uso, materiales, orden, consumo, gastado
     * @ai-ejemplo Materiales usados en orden 1234 | Consumo de materiales | Qué usamos
     * 
     * @param int $idOrden ID de la orden
     * @return string SQL query
     */
    public static function usoPorOrden(int $idOrden): string
    {
        return "
            SELECT 
                i.insumo,
                i.color,
                ABS(SUM(im.cantidad)) as cantidad_usada,
                i.unidad
            FROM inventario_movimientos im
            INNER JOIN inventario i ON im.id_insumo = i._id
            WHERE im.id_orden = {$idOrden}
            AND im.tipo = 'salida'
            GROUP BY i._id, i.insumo, i.color, i.unidad
            ORDER BY cantidad_usada DESC
        ";
    }

    /**
     * Stock de tintas
     * 
     * @ai-description Lista el inventario de tintas para impresión
     * @ai-keywords tintas, impresión, colores, sublimación
     * @ai-ejemplo Stock de tintas | Cuánta tinta tenemos | Inventario de tintas
     * 
     * @return string SQL query
     */
    public static function stockTintas(): string
    {
        return "
            SELECT 
                i._id,
                i.insumo,
                i.color,
                i.cantidad,
                i.unidad
            FROM inventario i
            INNER JOIN catalogo_insumos_productos cip ON i.id_catalogo = cip._id
            WHERE cip.nombre = 'Tinta' OR i.insumo LIKE '%tinta%'
            ORDER BY i.color, i.insumo
        ";
    }

    /**
     * Resumen de consumo de materiales por período
     * 
     * @ai-description Calcula cuánto material se consumió en un período
     * @ai-keywords consumo, gastado, usado, período, resumen
     * @ai-ejemplo Cuánto material gastamos este mes | Consumo de materiales
     * 
     * @param string|null $fechaInicio Fecha inicio
     * @param string|null $fechaFin Fecha fin
     * @return string SQL query
     */
    public static function resumenConsumo(?string $fechaInicio = null, ?string $fechaFin = null): string
    {
        $whereFecha = self::rangoFechas('im.moment', $fechaInicio, $fechaFin);

        return "
            SELECT 
                i.insumo,
                i.unidad,
                SUM(CASE WHEN im.tipo = 'entrada' THEN im.cantidad ELSE 0 END) as total_entradas,
                SUM(CASE WHEN im.tipo = 'salida' THEN ABS(im.cantidad) ELSE 0 END) as total_salidas,
                COUNT(DISTINCT im.id_orden) as ordenes_afectadas
            FROM inventario_movimientos im
            INNER JOIN inventario i ON im.id_insumo = i._id
            WHERE 1=1 {$whereFecha}
            GROUP BY i._id, i.insumo, i.unidad
            HAVING total_salidas > 0
            ORDER BY total_salidas DESC
        ";
    }
}
