<?php

require_once __DIR__ . '/BaseQuery.php';

/**
 * EmpleadosQueries - Consultas relacionadas con empleados
 * 
 * Contiene consultas SQL para:
 * - Búsqueda de empleados
 * - Información de empleados
 * - Estadísticas de trabajo
 * 
 * NOTA: Los empleados están en la BD externa api_empresas.empresas_usuarios
 * 
 * @package NineSys\Queries
 */
class EmpleadosQueries extends BaseQuery
{
    /**
     * Buscar empleado por nombre
     * 
     * @ai-description Busca empleados por nombre para obtener su ID
     * @ai-keywords empleado, buscar, nombre, id, usuario
     * @ai-ejemplo Buscar a Juan | ID de María | Empleado llamado Pedro
     * 
     * @param string $nombre Nombre o parte del nombre
     * @param int $idEmpresa ID de la empresa
     * @return string SQL query
     */
    public static function buscarPorNombre(string $nombre, int $idEmpresa): string
    {
        $where = self::likeClause('nombre', $nombre);

        return "
            SELECT 
                id_usuario,
                nombre,
                email,
                telefono,
                departamento,
                salario_tipo,
                comision
            FROM api_empresas.empresas_usuarios
            WHERE {$where}
            AND id_empresa = {$idEmpresa}
            AND activo = 1
        ";
    }

    /**
     * Lista de empleados activos
     * 
     * @ai-description Obtiene todos los empleados activos de la empresa
     * @ai-keywords empleados, activos, lista, todos, personal
     * @ai-ejemplo Lista de empleados | Todos los empleados | Personal activo
     * 
     * @param int $idEmpresa ID de la empresa
     * @return string SQL query
     */
    public static function empleadosActivos(int $idEmpresa): string
    {
        return "
            SELECT 
                id_usuario,
                nombre,
                email,
                telefono,
                departamento,
                salario_tipo,
                salario_monto,
                comision,
                fecha_ingreso
            FROM api_empresas.empresas_usuarios
            WHERE id_empresa = {$idEmpresa}
            AND activo = 1
            ORDER BY nombre
        ";
    }

    /**
     * Empleados por departamento
     * 
     * @ai-description Lista empleados de un departamento específico
     * @ai-keywords empleados, departamento, costura, corte, impresión
     * @ai-ejemplo Empleados de costura | Quién trabaja en corte | Personal de impresión
     * 
     * @param string $departamento Nombre del departamento
     * @param int $idEmpresa ID de la empresa
     * @return string SQL query
     */
    public static function porDepartamento(string $departamento, int $idEmpresa): string
    {
        return "
            SELECT 
                id_usuario,
                nombre,
                email,
                telefono,
                salario_tipo,
                comision
            FROM api_empresas.empresas_usuarios
            WHERE departamento = '{$departamento}'
            AND id_empresa = {$idEmpresa}
            AND activo = 1
            ORDER BY nombre
        ";
    }

    /**
     * Información completa de un empleado
     * 
     * @ai-description Obtiene toda la información de un empleado específico
     * @ai-keywords empleado, información, datos, detalle
     * @ai-ejemplo Datos del empleado 10 | Información de Juan
     * 
     * @param int $idEmpleado ID del empleado
     * @return string SQL query
     */
    public static function informacionEmpleado(int $idEmpleado): string
    {
        return "
            SELECT 
                id_usuario,
                nombre,
                email,
                telefono,
                departamento,
                dni,
                salario_monto,
                salario_periodo,
                salario_tipo,
                comision,
                comision_tipo,
                fecha_ingreso,
                activo
            FROM api_empresas.empresas_usuarios
            WHERE id_usuario = {$idEmpleado}
        ";
    }

    /**
     * Resumen de tipos de salario
     * 
     * @ai-description Cuenta empleados por tipo de salario
     * @ai-keywords salario, tipo, comisión, fijo, resumen
     * @ai-ejemplo Cuántos empleados por comisión | Resumen de tipos de salario
     * 
     * @param int $idEmpresa ID de la empresa
     * @return string SQL query
     */
    public static function resumenTiposSalario(int $idEmpresa): string
    {
        return "
            SELECT 
                salario_tipo,
                COUNT(*) as cantidad,
                AVG(salario_monto) as salario_promedio
            FROM api_empresas.empresas_usuarios
            WHERE id_empresa = {$idEmpresa}
            AND activo = 1
            GROUP BY salario_tipo
        ";
    }
}
