<?php
// File: src/Nomina/Strategies/VenezuelaNomina.php

namespace App\Lib;

use App\Lib\NominaStrategyInterface;
// Asegúrese de que sus clases de manejo de DB (e.g., DbQueryWrapper) 
// sean accesibles para las propiedades $dedicatedDb y $centralDb.

/**
 * Implementa la lógica de cálculo de nómina para Venezuela, 
 * siguiendo las reglas de la LOTTT y las normativas asociadas.
 */
class VenezuelaNomina implements NominaStrategyInterface
{
    private $paisId;
    private $dedicatedDb; // Conexión a la BD de la empresa (salario_incidencias, empresas_usuarios)
    private $centralDb;   // Conexión a la BD api_empresas (salario_reglas_legales, salario_conceptos_nomina)

    // --- CONSTRUCTOR ---
    public function __construct(int $paisId, $dedicatedDb, $centralDb)
    {
        $this->paisId = $paisId;
        $this->dedicatedDb = $dedicatedDb;
        $this->centralDb = $centralDb;
    }

    // --- MÉTODO PRINCIPAL DE ORQUESTACIÓN ---
    public function liquidarPeriodo(int $periodoId, int $usuarioId): array
    {
        // 1. Obtener datos del empleado, salario base y periodo de la BD dedicada
        $datosEmpleado = $this->dedicatedDb->fetchEmpleadoData($usuarioId);
        $incidencias = $this->dedicatedDb->fetchIncidencias($periodoId, $usuarioId);
        
        if (!$datosEmpleado) {
            throw new \Exception("Empleado ID {$usuarioId} no encontrado.");
        }

        // 2. Ejecutar cálculos
        $devengos = $this->calcularDevengos($incidencias);
        $deducciones = $this->calcularDeducciones($devengos['salario_base'], $devengos['total_gravable']);
        
        // 3. Provisión de Prestaciones Sociales (Específico de Venezuela)
        $this->provisionarPrestaciones($usuarioId);

        // 4. Registrar resultados en salario_resultados_nomina (BD Dedicada)
        $this->guardarResultadosNomina($periodoId, $usuarioId, $devengos, $deducciones);

        // 5. Devolver resumen para el controlador
        return [
            'total_devengos' => array_sum($devengos),
            'total_deducciones' => array_sum($deducciones),
            'neto_a_pagar' => array_sum($devengos) - array_sum($deducciones)
        ];
    }

    // --- CÁLCULO DE DEVENGOS (INGRESOS) ---
    public function calcularDevengos(array $incidencias): array
    {
        // 1. Obtener reglas de la BD Central
        $reglas = $this->centralDb->fetchReglasPorPais($this->paisId);
        
        // Valores de reglas clave (asumiendo que los fetch de DB son eficientes)
        $recargoExtra = $reglas['Recargo_Horas_Extra']['valor'] ?? 0.50;
        $recargoNocturno = $reglas['Recargo_Bono_Nocturno']['valor'] ?? 0.30;
        $recargoFeriado = $reglas['Recargo_Dias_Feriados']['valor'] ?? 1.50;

        $salarioBase = $incidencias['salario_monto'] ?? 0.00;
        $valorHora = $salarioBase / 30 / 8; // Asumiendo 30 días y jornada de 8 horas

        // 2. Cálculos Específicos
        
        // Horas Extras Diurnas (Gravable)
        $montoExtraDiurna = $incidencias['horas_extra_diurnas'] * $valorHora * (1 + $recargoExtra);

        // Feriados Trabajados (Gravable) - Aplica un recargo del 150% (total pagado es 250% del día)
        $montoFeriado = $incidencias['dias_feriados_trabajados'] * $valorHora * 8 * (1 + $recargoFeriado);

        // Comisiones (Gravable)
        $montoComision = $incidencias['monto_comision'] ?? 0.00;

        // Bono Alimenticio (No Gravable)
        $montoCestaticket = $incidencias['monto_bono_alimenticio'] ?? 0.00;

        // 3. Resultados
        $devengos = [
            'salario_base' => $salarioBase,
            'horas_extra_diurnas' => round($montoExtraDiurna, 2),
            'feriado_trabajado' => round($montoFeriado, 2),
            'comisiones' => round($montoComision, 2),
            'bono_alimenticio' => round($montoCestaticket, 2),
        ];

        // Se calcula el total gravable para usarlo en las deducciones
        $devengos['total_gravable'] = $salarioBase + $montoExtraDiurna + $montoFeriado + $montoComision;
        
        return $devengos;
    }

    // --- CÁLCULO DE DEDUCCIONES (RETENCIONES) ---
    public function calcularDeducciones(float $salarioBase, array $devengosTotales): array
    {
        // 1. Obtener reglas de la BD Central
        $reglas = $this->centralDb->fetchReglasPorPais($this->paisId);
        
        // Porcentajes típicos de la LOTTT (asumiendo que están en salario_reglas_legales)
        $ssoEmpleado = $reglas['Aporte_SSO_Trabajador']['valor'] ?? 0.04; // 4% estimado
        $faovEmpleado = $reglas['Aporte_FAOV_Trabajador']['valor'] ?? 0.01; // 1%
        // El paro forzoso es 0.5% pero a menudo se incluye en el SSO o se gestiona aparte.

        // 2. Cálculos Específicos

        // SSO (Salario base, con tope legal no considerado aquí para simplicidad)
        $deduccionSSO = $salarioBase * $ssoEmpleado;
        
        // FAOV
        $deduccionFAOV = $salarioBase * $faovEmpleado;
        
        // ISLR (Aquí iría la lógica compleja usando salario_tramos_impuesto)
        $deduccionISLR = $this->calcularISLR($devengosTotales); 
        
        // 3. Resultados
        return [
            'deduccion_sso' => round($deduccionSSO, 2),
            'deduccion_faov' => round($deduccionFAOV, 2),
            'deduccion_islr' => round($deduccionISLR, 2),
        ];
    }
    
    // --- PROVISIÓN DE PRESTACIONES SOCIALES (LOTTT) ---
    public function provisionarPrestaciones(int $usuarioId): bool
    {
        // ESTA ES LA LÓGICA MÁS ESPECÍFICA DE VENEZUELA
        // 1. Calcular Salario Integral y Salario Normal.
        // 2. Buscar si la provisión es semanal (días de salario integral) o mensual (15 días salario base).
        // 3. Almacenar el resultado en una nueva tabla (e.g., salario_prestaciones_acumuladas)
        
        // Por ahora, solo es una declaración de que el proceso existe.
        // Implementación requeriría más detalle y la nueva tabla de prestaciones.
        
        return true; 
    }

    // --- FUNCIÓN PRIVADA DE SOPORTE: ISLR ---
    private function calcularISLR(array $devengosTotales): float
    {
        // Lógica de Tramos de Impuesto
        // 1. Obtener tramos de la tabla salario_tramos_impuesto (BD Central)
        // 2. Aplicar el % de retención según el sueldo anual proyectado del empleado.
        
        // Devuelve 0 por simplicidad, la implementación real es compleja y necesita datos anuales.
        return 0.00; 
    }

    // --- FUNCIÓN PRIVADA DE SOPORTE: Guardar Resultados ---
    private function guardarResultadosNomina(int $periodoId, int $usuarioId, array $devengos, array $deducciones): void
    {
        // 1. Obtener IDs de concepto de la BD Central
        // (Ej: id_concepto para 'Salario Base', 'Retención ISLR')

        // 2. Insertar cada línea en salario_resultados_nomina (BD Dedicada)
        
        /* Pseudocódigo de Guardado:
        foreach ($devengos as $nombre => $monto) {
            $conceptoId = $this->centralDb->getConceptoId($nombre);
            $this->dedicatedDb->insertResultado($periodoId, $usuarioId, $conceptoId, $monto, false);
        }
        foreach ($deducciones as $nombre => $monto) {
            $conceptoId = $this->centralDb->getConceptoId($nombre);
            $this->dedicatedDb->insertResultado($periodoId, $usuarioId, $conceptoId, $monto * -1, true);
        }
        */
    }
}