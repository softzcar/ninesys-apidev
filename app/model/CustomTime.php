<?php
/**
 * Rectificar la hora en el servidor de bases de datos
 * Siteground no permite cambiar este parámetro
 * 
 */

class CustomTime
{
    protected $days;
    // private $pdo;

    // TODO crear parametro apra dias atrás en el reporte...

    public function __construct($days = "-32 days")
    {
        $this->newDate = new DateTime();
        $this->days = $days;
    }


    public function today()
    {
        $today = $this->newDate->format('Y-m-d H:i:s');
        return $today;
    }
    
    public function before()
    {
        // $before = $this->newDate->format('Y-m-d H:i:s');
        $before = $this->newDate->modify($this->days)->format('Y-m-d H:i:s');
        return $before;
    }
}
