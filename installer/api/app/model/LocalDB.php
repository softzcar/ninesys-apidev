<?php
/**
 * Almacenamiento en localhost
 * conectar con MongoDlocalhost
 * Conectar conr emoto
 */

class LocalDB
{
    protected $sql;
    private $pdo;

    public function __construct($sql)
    {
        $this->sql = $sql;
        $this->pdo = new PDO(
            LOCAL_DSN,
            LOCAL_USER,
            LOCAL_PASS,
            array(
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET lc_time_names = 'es_ES', NAMES utf8"
            )
        );
    }


    public function insert()
    {
        $mat = array();
        try {
            // Crear nueva orden
            $sql = $this->sql;
            $res = $this->pdo->prepare($sql);
            $res->execute();
            $mat = $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            $mat['status'] = 'error';
            $mat['message'] = $e->getMessage();
        }
        // unset($this->pdo); // Cerrar la conexión

        return $mat;
    }

    public function goQuery()
    {
        $mat = array();
        try {
            $sql = $this->sql;

            $res = $this->pdo->prepare($sql);
            $res->execute();

            /* // VERIFICAR SI LA CONDULTA ES UN INSERT PARA OBTENER EL LAST ID
            $explode = explode($this->sql, " ");
            $sentenceType = $explode[0];
            if($sentenceType == "INSERT") {
            if($res->execute()) {
            $vectorID = $this->pdo->query("SELECT LAST_INSERT_ID()")->fetch();
            $last_id = $vectorID;
            }
            } else {
            $data = $res->fetchAll(PDO::FETCH_ASSOC);
            } */

            $data = $res->fetchAll(PDO::FETCH_ASSOC);
            $mat = $data;

            // $mat['status']  = 'success';
        } catch (PDOException $e) {
            $mat['status'] = 'error';
            $mat['message'] = $e->getMessage();
        }
        // unset($this->pdo); // Cerrar la conexión
        return $mat;
    }

    public function getLastID()
    {
        return $this->pdo->lastInsertId();

    }
}