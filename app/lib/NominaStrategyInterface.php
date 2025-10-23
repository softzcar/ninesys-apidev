<?php
// File: src/Nomina/Strategies/NominaStrategyInterface.php

namespace App\Nomina\Strategies;

/**
 * Define el contrato que toda clase de cálculo de nómina específica por país
 * (Strategy) debe implementar.
 */
interface NominaStrategyInterface
{
    /**
     * Constructor: Recibe las dependencias de base de datos necesarias para operar.
     * * @param int $paisId ID de la tabla salario_paises para buscar reglas.
     * @param object $dedicatedDb Conexión a la BD dedicada de la empresa (para incidencias, empleados, etc.).
     * @param object $centralDb Conexión a la BD central (para reglas legales, conceptos).
     */
    public function __construct(int $paisId, $dedicatedDb, $centralDb);

    /**
     * Método principal que orquesta todo el cálculo, desde devengos hasta deducciones,
     * y guarda el resultado en la tabla salario_resultados_nomina.
     * * @param int $periodoId ID del período de nómina a liquidar.
     * @param int $usuarioId ID del empleado (empresas_usuarios.id_usuario).
     * @return array Un resumen de los resultados de la liquidación.
     */
    public function liquidarPeriodo(int $periodoId, int $usuarioId): array;

    /**
     * Calcula todos los ingresos del empleado (salario base, horas extra, bonos, comisiones).
     * * @param array $incidencias Datos de salario_incidencias_x_empleado.
     * @return array Resultados detallados de los devengos.
     */
    public function calcularDevengos(array $incidencias): array;
    
    /**
     * Calcula todas las retenciones y deducciones legales (ISLR, SSO, FAOV, etc.).
     * * @param float $salarioBase Salario base usado para calcular aportes porcentuales.
     * @param array $devengosTotales Suma total de los devengos gravables.
     * @return array Resultados detallados de las deducciones.
     */
    public function calcularDeducciones(float $salarioBase, array $devengosTotales): array;

    /**
     * Opcional: Calcula las prestaciones sociales (específico de Venezuela).
     * * @param int $usuarioId ID del empleado.
     * @return bool True si la provisión fue actualizada.
     */
    public function provisionarPrestaciones(int $usuarioId): bool;
}