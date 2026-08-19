<?php
class LocalDB
{
  protected $sql;
  private $pdo = null;
  private $dsn;
  private $user;
  private $pass;

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
    // Si ya existe una conexión, no crear una nueva (preserva transacciones)
    if ($this->pdo !== null) {
      return;
    }

    try {
      // Forzar localhost para evitar cuelgues de TCP con 127.0.0.1 en entornos LiteSpeed
      $sanitizedDSN = str_replace('127.0.0.1', 'localhost', $this->dsn);
      $timezoneOffset = date('P');
      $isPgsql = (strpos($this->dsn, 'pgsql:') === 0);

      if ($isPgsql) {
        $this->pdo = new PDO($this->dsn, $this->user, $this->pass);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec("SET client_encoding TO 'UTF8';");
        // Zona horaria por empresa (país configurado), no un offset fijo de
        // servidor -- IdEmpresaMiddleware ya resolvió EMPRESA_TIMEZONE (ej.
        // "America/Bogota") como NOMBRE de zona, nunca offset numérico:
        // Postgres interpreta los offsets numéricos "pelados" (ej. "-04:00")
        // con el signo invertido (convención POSIX) -- ese fue exactamente
        // el bug real encontrado el 2026-08-03 (8 horas de desfase, cuando
        // esto usaba `date('P')` directo). Los nombres de zona no tienen ese
        // problema (verificado en vivo). Si la zona no se pudo resolver o no
        // es válida, cae al default del propio clúster (America/Caracas, ya
        // fijado en postgresql.conf) sin tumbar la conexión.
        $tz = (defined('EMPRESA_TIMEZONE') && EMPRESA_TIMEZONE) ? EMPRESA_TIMEZONE : 'America/Caracas';
        try {
          $this->pdo->exec('SET TIME ZONE ' . $this->pdo->quote($tz) . ';');
        } catch (\Exception $e) {}
        try {
          $this->pdo->exec("SET lc_time TO 'es_ES.UTF-8';");
        } catch (\Exception $e) {}
      } else {
        $this->pdo = new PDO(
          $sanitizedDSN,
          $this->user,
          $this->pass,
          array(
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET lc_time_names = 'es_ES', NAMES utf8; SET time_zone = '{$timezoneOffset}';"
          )
        );
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      }
    } catch (PDOException $e) {
      throw $e;  // Re-throw the exception to be caught by the calling code
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
    $driver = getenv('DB_DRIVER') ?: 'mysql';

    if ($driver === 'pgsql') {
      // Conectar a la base de datos api_empresas
      $this->switchDatabase(EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);
      // Obtener los empleados de api_empresas
      $employees = $this->goQuery('SELECT * FROM empresas_usuarios WHERE id_empresa = ' . $id_empresa);

      // Obtener detalles de conexión para la empresa
      $data_empresa = $this->getConnectionDetails($id_empresa);
      $port = getenv('DB_PORT') ?: '5432';
      $LOCAL_DNS = 'pgsql:host=' . $data_empresa['db_host'] . ';port=' . $port . ';dbname=' . $data_empresa['db_name'];
      $LOCAL_USER = $data_empresa['db_user'];
      $LOCAL_PASS = $data_empresa['db_password'];

      // Conectar a la base de datos específica de la empresa
      $this->switchDatabase($LOCAL_DNS, $LOCAL_USER, $LOCAL_PASS);

      // Vaciar la tabla empleados existente
      $this->goQuery('DELETE FROM empleados;');

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
    } else {
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
    $this->pdo = null;  // Desconectar cerrando la conexión PDO
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

      // Si la consulta es un INSERT, obtener el ID generado. En Postgres,
      // lastInsertId() ejecuta lastval(), que lanza SQLSTATE[55000] si la
      // tabla no tiene columna serial/identity (ej. tablas de relación con
      // PK compuesta como impresoras_colores) y ninguna secuencia se tocó
      // aún en esta sesión -- no es un error real, solo "esta tabla no
      // genera ID autoincremental". Dentro de una transacción explícita,
      // Postgres marca TODA la transacción como abortada en cuanto lastval()
      // falla (aunque PHP capture la excepción), así que además hay que
      // aislar el intento con un SAVEPOINT descartable para no arrastrar ese
      // error a las siguientes queries de la misma transacción.
      if (preg_match('/^\s*INSERT\s+/i', $sql)) {
        if (DB_DRIVER === 'pgsql' && $this->pdo->inTransaction()) {
          try {
            $this->pdo->exec('SAVEPOINT goquery_lastid');
            $mat['insert_id'] = $this->pdo->lastInsertId();
            $this->pdo->exec('RELEASE SAVEPOINT goquery_lastid');
          } catch (PDOException $e) {
            $this->pdo->exec('ROLLBACK TO SAVEPOINT goquery_lastid');
            $mat['insert_id'] = null;
          }
        } else {
          try {
            $mat['insert_id'] = $this->pdo->lastInsertId();
          } catch (PDOException $e) {
            $mat['insert_id'] = null;
          }
        }
      } else {
        $data = $res->fetchAll(PDO::FETCH_ASSOC);
        $mat = $data;
      }
    } catch (PDOException $e) {
      // Red de seguridad para claves foráneas: las violaciones de integridad
      // referencial NO deben quedar como un retorno silencioso (los llamadores
      // suelen no verificar el 'status'). Se propagan al manejador de errores
      // central de Slim (HttpErrorHandler), que responde un HTTP 409 limpio.
      $sqlState = $e->errorInfo[0] ?? '';
      $driverErrno = (is_array($e->errorInfo ?? null) && isset($e->errorInfo[1])) ? (int) $e->errorInfo[1] : 0;
      $foreignKeyErrnos = [1451, 1452, 1216, 1217];
      if ($sqlState === '23503' || in_array($driverErrno, $foreignKeyErrnos, true)) {
        throw new \App\Application\Exceptions\DatabaseConstraintException(
          'No se puede completar la operación porque viola una relación de datos (clave foránea). DEBUG_TEMPORAL: ' . $e->getMessage(),
          0,
          $e
        );
      }

      // Duplicados por índice único: se preserva el retorno "suave" (status=error
      // en el array) porque varios endpoints lo usan intencionalmente para mostrar
      // mensajes de "ya existe" sin que sea un 500.
      $uniqueViolationErrnos = [1062];
      if ($sqlState === '23505' || in_array($driverErrno, $uniqueViolationErrnos, true)) {
        $mat['sql'] = $sql;
        $mat['status'] = 'error';
        $mat['message'] = 'Error al ejecutar la consulta: ' . $e->getMessage();
        return $mat;
      }

      // Red de seguridad general: cualquier OTRO error SQL (sintaxis, columna
      // inexistente, tipo de dato ambiguo, etc.) indica un bug real, no una regla
      // de negocio esperada. Se registra explícitamente y se propaga como
      // excepción real -> HTTP 500 honesto, en vez de devolver silenciosamente un
      // array que la mayoría de los llamadores no verifica y puede malinterpretar
      // como datos válidos (la app no debe fingir éxito cuando la consulta falló).
      error_log('goQuery() error SQL no manejado: ' . $e->getMessage() . ' | SQL: ' . $sql);
      throw new \App\Application\Exceptions\DatabaseQueryException(
        'Error interno al ejecutar una operación de base de datos.',
        0,
        $e
      );
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

  public function getEmpresaPaisId($id_empresa)
  {
    // Conectar a la base de datos api_empresas para obtener los detalles de conexión
    $this->switchDatabase(EMPRESAS_DNS, EMPRESAS_USER, EMPRESAS_PASS);

    $sql = 'SELECT pais FROM empresas WHERE id_empresa = :id_empresa';
    $res = $this->pdo->prepare($sql);
    $res->execute(['id_empresa' => $id_empresa]);
    return $res->fetch(PDO::FETCH_ASSOC);
  }

  /**
   * Inicia una transacción de base de datos
   * Todas las operaciones siguientes se ejecutarán como una unidad atómica
   * 
   * @return bool
   */
  public function beginTransaction()
  {
    $this->connectToDatabase();
    return $this->pdo->beginTransaction();
  }

  /**
   * Confirma la transacción actual
   * Guarda permanentemente todos los cambios realizados desde beginTransaction()
   * 
   * @return bool
   */
  public function commit()
  {
    return $this->pdo->commit();
  }

  /**
   * Revierte la transacción actual
   * Deshace todos los cambios realizados desde beginTransaction()
   * 
   * @return bool
   */
  public function rollback()
  {
    return $this->pdo->rollBack();
  }

  /**
   * Verifica si hay una transacción activa
   * 
   * @return bool
   */
  public function inTransaction()
  {
    return $this->pdo !== null && $this->pdo->inTransaction();
  }
}
