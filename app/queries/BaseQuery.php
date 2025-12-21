<?php

/**
 * BaseQuery - Clase base para todas las consultas SQL centralizadas
 * 
 * Esta clase proporciona métodos helper comunes para construir consultas SQL.
 * Las clases hijas extienden esta clase y definen consultas específicas por dominio.
 * 
 * @package NineSys\Queries
 */
abstract class BaseQuery
{
    /**
     * Escapa valores para prevenir SQL injection
     * 
     * @param mixed $value Valor a escapar
     * @return string Valor escapado
     */
    protected static function escape($value): string
    {
        if (is_null($value)) {
            return 'NULL';
        }
        if (is_numeric($value)) {
            return (string) $value;
        }
        // Escapar comillas simples
        return "'" . addslashes($value) . "'";
    }

    /**
     * Construye condición de rango de fechas
     * 
     * @param string $campo Nombre del campo de fecha
     * @param string|null $fechaInicio Fecha inicio (Y-m-d)
     * @param string|null $fechaFin Fecha fin (Y-m-d)
     * @return string Condición SQL o string vacío
     */
    protected static function rangoFechas(string $campo, ?string $fechaInicio, ?string $fechaFin): string
    {
        if ($fechaInicio && $fechaFin) {
            return " AND DATE({$campo}) BETWEEN '{$fechaInicio}' AND '{$fechaFin}'";
        }
        if ($fechaInicio) {
            return " AND DATE({$campo}) >= '{$fechaInicio}'";
        }
        if ($fechaFin) {
            return " AND DATE({$campo}) <= '{$fechaFin}'";
        }
        return '';
    }

    /**
     * Construye condición IN para múltiples valores
     * 
     * @param string $campo Nombre del campo
     * @param array $valores Array de valores
     * @return string Condición SQL
     */
    protected static function inClause(string $campo, array $valores): string
    {
        if (empty($valores)) {
            return '1=0'; // Nunca coincide si está vacío
        }
        $escaped = array_map([self::class, 'escape'], $valores);
        return "{$campo} IN (" . implode(', ', $escaped) . ")";
    }

    /**
     * Construye condición LIKE para búsqueda
     * 
     * @param string $campo Nombre del campo
     * @param string $valor Valor a buscar
     * @param string $tipo 'contains', 'starts', 'ends'
     * @return string Condición SQL
     */
    protected static function likeClause(string $campo, string $valor, string $tipo = 'contains'): string
    {
        $escaped = addslashes($valor);
        switch ($tipo) {
            case 'starts':
                return "{$campo} LIKE '{$escaped}%'";
            case 'ends':
                return "{$campo} LIKE '%{$escaped}'";
            default:
                return "{$campo} LIKE '%{$escaped}%'";
        }
    }
}
