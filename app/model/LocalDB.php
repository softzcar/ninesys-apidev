<?php
class LocalDB
{
    protected $sql;
    private $pdo;

    /*  public function __construct($sql = '')
     *     {
     *         $this->sql = $sql;
} */

    public function __construct($sql = '', $dsn = LOCAL_DNS, $user = LOCAL_USER, $pass = LOCAL_PASS)
    {
        $this->sql = $sql;
        $this->dsn = $dsn;
        $this->user = $user;
        $this->pass = $pass;
    }

    private function connectToDatabase()
    {
        try {
            $this->pdo = new PDO(
                $this->dsn,
                $this->user,
                $this->pass,
                array(
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET lc_time_names = 'es_ES', NAMES utf8"
                )
            );
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die('Database connection failed: ' . $e->getMessage());
        }
    }

    public function switchDatabase($dsn, $user, $pass)
    {
        $this->disconnect();  // Desconectar la conexión anterior
        $this->dsn = $dsn;
        $this->user = $user;
        $this->pass = $pass;
        $this->connectToDatabase();  // Conectar a la nueva base de datos
    }

    public function syncEmpleados($id_empresa)
    {
        // Conectar a la base de datos api_empresas
        $this->switchDatabase(EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);

        // Eliminar temporalmente la restricción de clave foránea
        $this->goQuery('ALTER TABLE empresas_usuarios DROP FOREIGN KEY fk_id_empresa');

        // Obtener la estructura de la tabla empresas_usuarios sin la clave foránea
        $tableStructure = $this->goQuery('SHOW CREATE TABLE empresas_usuarios');
        $createTableSQL = $tableStructure[0]['Create Table'];

        // Reemplazar el nombre de la tabla
        $createTableSQL = str_replace('empresas_usuarios', 'empleados', $createTableSQL);

        // Log de la sentencia CREATE TABLE para depuración
        error_log('CREATE TABLE SQL: ' . $createTableSQL);

        // Obtener los empleados de api_empresas
        $employees = $this->goQuery('SELECT * FROM empresas_usuarios WHERE id_empresa = ' . $id_empresa);

        // Restablecer la clave foránea en la tabla original
        $this->goQuery('ALTER TABLE empresas_usuarios ADD CONSTRAINT fk_id_empresa FOREIGN KEY (id_empresa) REFERENCES empresas(id_empresa) ON DELETE CASCADE');

        // Obtener detalles de conexión para la empresa
        $data_empresa = $this->getConnectionDetails($id_empresa);
        $LOCAL_DNS = 'mysql:host=' . $data_empresa['db_host'] . ';dbname=' . $data_empresa['db_name'];
        $LOCAL_USER = $data_empresa['db_user'];
        $LOCAL_PASS = $data_empresa['db_password'];

        // Conectar a la base de datos específica de la empresa
        $this->switchDatabase($LOCAL_DNS, $LOCAL_USER, $LOCAL_PASS);

        // Eliminar y recrear la tabla empleados
        $this->goQuery('SET FOREIGN_KEY_CHECKS = 0;');
        $this->goQuery('DROP TABLE IF EXISTS empleados');

        // Agregar una revisión aquí para asegurar que DROP TABLE se ejecutó correctamente
        error_log('Tabla empleados eliminada');

        // Ejecutar la sentencia CREATE TABLE
        $createResult = $this->goQuery($createTableSQL);

        // Verificar el resultado de la creación de la tabla
        if (isset($createResult['status']) && $createResult['status'] === 'error') {
            error_log('Error al crear la tabla: ' . $createResult['message']);
            throw new Exception('Error al crear la tabla empleados: ' . $createResult['message']);
        }

        $this->goQuery('SET FOREIGN_KEY_CHECKS = 1;');

        // Insertar empleados en la tabla empleados
        foreach ($employees as $employee) {
            $sql = 'INSERT INTO empleados (id_usuario, email, password, nombre, departamento, id_empresa, activo, acceso, comision, moment, fecha_actualizacion)
        VALUES (:id_usuario, :email, :password, :nombre, :departamento, :id_empresa, :activo, :acceso, :comision, :moment, :fecha_actualizacion)';
            $params = [
                ':id_usuario' => $employee['id_usuario'],
                ':email' => $employee['email'],
                ':password' => $employee['password'],
                ':nombre' => $employee['nombre'],
                ':departamento' => $employee['departamento'],
                ':id_empresa' => $employee['id_empresa'],
                ':activo' => $employee['activo'],
                ':acceso' => $employee['acceso'],
                ':comision' => $employee['comision'],
                ':moment' => $employee['moment'],
                ':fecha_actualizacion' => $employee['fecha_actualizacion'],
            ];
            $this->goQuery($sql, $params);
        }
    }

    public function syncEmpleados_en_revision($id_empresa)
    {
        // Conectar a la base de datos api_empresas
        $this->switchDatabase(EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);

        // Obtener la estructura de la tabla empresas_usuarios
        $tableStructure = $this->goQuery('SHOW CREATE TABLE empresas_usuarios');
        $createTableSQL = $tableStructure[0]['Create Table'];
        $createTableSQL = str_replace('empresas_usuarios', 'empleados', $createTableSQL);

        // Obtener los empleados de api_empresas
        $employees = $this->goQuery('SELECT * FROM empresas_usuarios WHERE id_empresa = ' . $id_empresa);

        // Obtener detalles de conexión para la empresa
        $data_empresa = $this->getConnectionDetails($id_empresa);
        $LOCAL_DNS = 'mysql:host=' . $data_empresa['db_host'] . ';dbname=' . $data_empresa['db_name'];
        $LOCAL_USER = $data_empresa['db_user'];
        $LOCAL_PASS = $data_empresa['db_password'];

        // Conectar a la base de datos específica de la empresa
        $this->switchDatabase($LOCAL_DNS, $LOCAL_USER, $LOCAL_PASS);

        // Eliminar y recrear la tabla empleados
        $this->goQuery('SET FOREIGN_KEY_CHECKS = 0;');

        $this->goQuery('DROP TABLE IF EXISTS empleados');
        $this->goQuery($createTableSQL);
        /* $this->goQuery('SET FOREIGN_KEY_CHECKS = 1;');
// Insertar empleados en la tabla empleados
        foreach ($employees as $employee) {
            $sql = 'INSERT INTO empleados (id_usuario, email, password, nombre, departamento, id_empresa, activo, acceso, comision, moment, fecha_actualizacion)
            VALUES (:id_usuario, :email, :password, :nombre, :departamento, :id_empresa, :activo, :acceso, :comision, :moment, :fecha_actualizacion)';
            $params = [
                ':id_usuario' => $employee['id_usuario'],
                ':email' => $employee['email'],
                ':password' => $employee['password'],
                ':nombre' => $employee['nombre'],
                ':departamento' => $employee['departamento'],
                ':id_empresa' => $employee['id_empresa'],
                ':activo' => $employee['activo'],
                ':acceso' => $employee['acceso'],
                ':comision' => $employee['comision'],
                ':moment' => $employee['moment'],
                ':fecha_actualizacion' => $employee['fecha_actualizacion'],
            ];
            $this->goQuery($sql, $params);
        } */
    }

    public function disconnect()
    {
        unset($this->pdo);  // Desconectar cerrando la conexión PDO
    }

    public function setSql($sql)
    {
        $this->sql = $sql;
    }

    public function insert()
    {
        $mat = array();
        try {
            // Crear nueva orden
            $res = $this->pdo->prepare($this->sql);
            $res->execute();
            $mat = $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            $mat['status'] = 'error';
            $mat['message'] = $e->getMessage();
        }

        return $mat;
    }

    public function goQuery($sql = '', $params = [])
    {
        $this->connectToDatabase();
        $mat = array();
        try {
            $res = $this->pdo->prepare($sql);
            $res->execute($params);

            // Si la consulta es un INSERT, obtener el ID generado
            if (preg_match('/^\s*INSERT\s+/i', $sql)) {
                $mat['insert_id'] = $this->pdo->lastInsertId();
            } else {
                $data = $res->fetchAll(PDO::FETCH_ASSOC);
                $mat = $data;
            }
        } catch (PDOException $e) {
            $mat['sql'] = $sql;
            $mat['status'] = 'error';
            $mat['message'] = 'Error al ejecutar la consulta: ' . $e->getMessage();
        }

        return $mat;
    }

    /* public function goQuery($sql = '', $params = [])
    {
        $this->connectToDatabase();
        $mat = array();
        try {
            $res = $this->pdo->prepare($sql);
            $res->execute($params);

            // Si es una consulta INSERT, obtener el último ID insertado
            // if (stripos(trim($sql), 'INSERT') === 0) {
              //  $mat['last_insert_id'] = $this->pdo->lastInsertId();
            // }
            $mat['last_insert_id'] = $this->pdo->lastInsertId();
            $mat['status'] = 'success';

            $data = $res->fetchAll(PDO::FETCH_ASSOC);
            $mat = $data;
        } catch (PDOException $e) {
            // $mat['sql'] = $sql;
            $mat['status'] = 'error';
            $mat['message'] = 'Error al ejecutar la consulta: ' . $e->getMessage();
        }

        return $mat;
    } */

    /* public function goQuery_old($sql = '')
    {
        $this->connectToDatabase();
        $mat = array();
        try {
            $res = $this->pdo->prepare($sql);
            $res->execute();

            $data = $res->fetchAll(PDO::FETCH_ASSOC);
            $mat = $data;
            // $lastInsertId = $this->pdo->lastInsertId(); // Obtener el ID
            // $mat['last_insert_id'] = $lastInsertId; // Agregar el ID a los datos
        } catch (PDOException $e) {
            $errorInfo = $e->errorInfo();
            $mat['sql'] = $this->sql;
            $mat['status'] = 'error';
            $mat['message'] = 'Error al ejecutar la consulta: ' . $e->getMessage() . '. Detalles: ' . $errorInfo[2];
        }

        return $mat;
    } */

    public function getLastID()
    {
        return $this->pdo->lastInsertId();
    }

    public function getConnectionDetails($id_empresa)
    {
        // Conectar a la base de datos api_empresas para obtener los detalles de conexión
        $this->switchDatabase(EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);

        $sql = 'SELECT db_host, db_user, db_password, db_name FROM empresas WHERE id_empresa = :id_empresa';
        $res = $this->pdo->prepare($sql);
        $res->execute(['id_empresa' => $id_empresa]);
        return $res->fetch(PDO::FETCH_ASSOC);
    }
}
