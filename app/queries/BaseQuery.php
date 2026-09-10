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
     * Estos helpers construyen fragmentos de SQL ya embebidos con el valor
     * (no placeholders `?`), pensados para ensamblarse dentro de las
     * consultas de este directorio (potencialmente usadas por un motor que
     * arma SQL a partir de texto, ver `@ai-*` en las clases hijas) -- por
     * eso el escapado tiene que ser real aquí, no delegable a un binding.
     *
     * addslashes() NO protege nada contra esta base de datos: Postgres
     * corre con standard_conforming_strings=on, así que el backslash no es
     * carácter de escape dentro de un literal '...' -- confirmado en vivo
     * (auditoría de seguridad 2026-09-10, ver memoria de seguridad,
     * ejecutó un DROP TABLE inyectado a través de ese mismo patrón). Se usa
     * en su lugar el escape estándar SQL (duplicar la comilla simple, `''`),
     * que sí funciona en Postgres sin importar standard_conforming_strings;
     * en MySQL además se duplica la barra invertida porque ahí sí es
     * carácter de escape por defecto.
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
        $escaped = (string) $value;
        if (defined('DB_DRIVER') && DB_DRIVER !== 'pgsql') {
            $escaped = str_replace('\\', '\\\\', $escaped);
        }
        $escaped = str_replace("'", "''", $escaped);
        return "'" . $escaped . "'";
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
        // Antes interpolaba $fechaInicio/$fechaFin directo, sin ningún
        // escapado -- ni siquiera el addslashes() roto que tenían los otros
        // helpers de esta clase. Corregido 2026-09-10 reutilizando escape().
        if ($fechaInicio && $fechaFin) {
            return " AND DATE({$campo}) BETWEEN " . self::escape($fechaInicio) . " AND " . self::escape($fechaFin);
        }
        if ($fechaInicio) {
            return " AND DATE({$campo}) >= " . self::escape($fechaInicio);
        }
        if ($fechaFin) {
            return " AND DATE({$campo}) <= " . self::escape($fechaFin);
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
        // self::escape() ya devuelve el valor entre comillas -- se le quita
        // aquí la comilla de cierre/apertura para poder intercalar el '%'
        // de LIKE sin dejar de pasar por el escapado real.
        $escapedQuoted = self::escape($valor);
        $escaped = substr($escapedQuoted, 1, -1);
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
