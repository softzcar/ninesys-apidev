<?php
// File: /app/lib/NominaBase.php

/**
 * Clase Base abstracta para el cálculo de nómina de cualquier país.
 * Define la estructura común y las dependencias (DBs).
 */
abstract class NominaBase
{
    protected $paisId;
    protected $idEmpleado;
    protected $dedicatedDb; // Conexión a la BD de la empresa
    protected $centralDb;   // Conexión a la BD api_empresas
    protected $conceptosNomina;
    protected $reglasLegales;


    public function __construct(int $paisId, int $idEmpleado, $dedicatedDb, $centralDb)
    {
        $this->paisId = $paisId;
        $this->idEmpleado = $idEmpleado;
        $this->dedicatedDb = $dedicatedDb;
        $this->centralDb = $centralDb;
    }

    /**
     * El método central de cálculo que debe ser implementado por cada país.
     */
    abstract public function liquidarPeriodo(int $periodoId): array;

    public function verificarEmpleado() 
    {
      $sql = "SELECT id_usuario FROM empresas_usuarios WHERE id_usuario = $this->idEmpleado AND id_empresa = " . ID_EMPRESA;

      $respEmp = $this->dedicatedDb->goQuery($sql);
      $this->dedicatedDb->disconnect();

      if (empty($respEmp) || !isset($respEmp[0]['id_usuario'])) {
        return false;
      }

      return true;
    }

    public function getConceptosNomina()
    {      
      $sql = "SELECT * FROM salario_conceptos_nomina";
        $this->conceptosNomina = $this->centralDb->goQuery($sql);
        $this->centralDb->disconnect();
        return $this->conceptosNomina;
    
    }

    public function getDivisorDeSalario()
    {
      $sql = "SELECT salario_periodo FROM empresas_usuarios WHERE id_usuario = ". $this->idEmpleado;
      // return $sql;
      $resPeriodo = $this->centralDb->goQuery($sql);
      $this->centralDb->disconnect();

      $divisor = 1;

      if ($resPeriodo[0]['salario_periodo'] === "mensual") $divisor = 1;
      if ($resPeriodo[0]['salario_periodo'] === "quincenal") $divisor = 2;
      if ($resPeriodo[0]['salario_periodo'] === "semanal") $divisor = 4;

      return $divisor;
    }

    public function getSalarioData()
    {
      $sql = "SELECT salario_monto, salario_periodo, salario_tipo, comision comision_fija, comision_tipo, comision_porcentaje FROM empresas_usuarios WHERE id_usuario = ". $this->idEmpleado;
      $resEmp = $this->centralDb->goQuery($sql);
      $this->centralDb->disconnect();

      return [
        'salario_monto' => floatval($resEmp[0]['salario_monto']),
        'salario_periodo' => $resEmp[0]['salario_periodo'],
        'salario_tipo' => $resEmp[0]['salario_tipo'],
        'comision_tipo' => $resEmp[0]['comision_tipo'],
        'comision_fija' => $resEmp[0]['comision_fija'],
        'comision_porcentaje' => floatval($resEmp[0]['comision_porcentaje'])
      ];
    }

    public function getIdPais() 
    {
      return $this->paisId;
    }
    
    public function getIdempelado() 
    {
      return $this->idEmpleado;
    }
    
    public function getReglasLegales()
    {      
      $sql = "SELECT * FROM salario_reglas_legales WHERE id_pais = ". $this->paisId;
        $this->reglasLegales = $this->centralDb->goQuery($sql); 
        return $this->reglasLegales;
    
    }
}