<?php
// File: /app/lib/NominaEngine.php

namespace App\Lib;

// Importar la interfaz y las estrategias
use App\Lib\NominaStrategyInterface;
use App\Lib\VenezuelaNomina; // <--- CORREGIDO (Asumiendo que VenezuelaNomina está en App\Lib)

/**
 * Motor central de cálculo de nómina. Selecciona la estrategia de país adecuada.
 */
class NominaEngine
{
    private $dedicatedDb; // Conexión a la BD de la empresa
    private $centralDb;   // Conexión a la BD api_empresas

    // Mapeo de ID de País a la Clase Estrategia (Strategy Map)
    // Se recomienda obtener estos IDs de forma dinámica de salario_paises si es posible,
    // pero para simplicidad inicial, se define estáticamente.
    private $countryStrategyMap = [
        58 => VenezuelaNomina::class, // ID 1 de salario_paises = Venezuela
        // Añadir aquí: 2 => ColombiaNomina::class, etc.
    ];

    /**
     * Constructor que recibe las conexiones de DB inyectadas.
     */
    public function __construct($dedicatedDb, $centralDb)
    {
        $this->dedicatedDb = $dedicatedDb;
        $this->centralDb = $centralDb;
    }

    /**
     * Determina la estrategia de nómina adecuada y ejecuta la liquidación.
     * @param int $periodoId ID del período de nómina.
     * @param int $usuarioId ID del empleado.
     * @return array Resultado de la liquidación del período.
     * @throws \Exception Si la estrategia para el país no está definida.
     */
    public function liquidar(int $periodoId, int $usuarioId): array
    {
        // 1. Obtener el ID del País de la empresa.
        // ASUMIMOS que esta función existe en tu wrapper de DB dedicada.
        // $paisId = $this->dedicatedDb->getEmpresaPaisId(ID_EMPRESA); 
        // $localConnection = new LocalDB('', EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);
        $sql = "SELECT pais FROM empresas WHERE id_empresa = " . ID_EMPRESA;
        $paisResponse = $this->centralDb->goQuery($sql);

        $paisId = $paisResponse[0]['pais'];
        // return $paisId;

        // 2. Seleccionar la clase estrategia.
        if (!isset($this->countryStrategyMap[$paisId])) {
            throw new \Exception("Estrategia de nómina no definida para el país ID: {$paisId}.");
          } else {
        }

        $strategyClass = $this->countryStrategyMap[$paisId];
        
        // 3. Instanciar la estrategia concreta e inyectarle las conexiones.
        /** @var NominaStrategyInterface $strategy */
        $strategy = new $strategyClass($paisId, $this->dedicatedDb, $this->centralDb);

        // 4. Ejecutar el cálculo específico.
        return $strategy->liquidarPeriodo($periodoId, $usuarioId);
    }
}