<?php
/**
 * Migration: crear tabla reposiciones_departamentos_excluidos
 * ELIMINAR ESTE ARCHIVO TRAS EJECUTAR
 */

// Bootstrap completo de la aplicación para tener acceso a LocalDB con las constantes correctas
define('RUNNING_MIGRATION', true);

// Cargar el entorno igual que lo hace index.php
$rootDir = realpath(__DIR__ . '/../../');
require_once $rootDir . '/app/lib/config.php';

// El middleware de empresa resuelve LOCAL_DNS dinámicamente.
// Para migraciones usamos las variables de entorno del servidor directamente.
$envFile = $rootDir . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($key, $val) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($val);
            putenv(trim($key) . '=' . trim($val));
        }
    }
}

// Intentar resolver la DSN a través del header Host o de variables de entorno
$host = $_SERVER['HTTP_HOST'] ?? 'api.nineteengreen.com';

// Buscar en api_empresas cuál es la BD asignada a este host
$empresasDsn  = defined('EMPRESAS_DNS')  ? EMPRESAS_DNS  : ('mysql:host=' . (getenv('DB_HOST') ?: 'localhost') . ';dbname=' . (getenv('DB_NAME') ?: 'api_empresas'));
$empresasUser = defined('EMPRESAS_USER') ? EMPRESAS_USER : getenv('DB_USER');
$empresasPass = defined('EMPRESAS_PASS') ? EMPRESAS_PASS : getenv('DB_PASS');

try {
    $pdoEmpresas = new PDO($empresasDsn, $empresasUser, $empresasPass, [
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8",
    ]);
    $pdoEmpresas->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Buscar BD por dominio
    $stmt = $pdoEmpresas->prepare(
        "SELECT e.bd, e.bd_user, e.bd_password, e.bd_host
         FROM empresas e
         JOIN empresas_dominios ed ON ed.id_empresa = e._id
         WHERE ed.dominio = ?
         LIMIT 1"
    );
    $stmt->execute([$host]);
    $empresa = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$empresa) {
        // Intentar sin subdominio
        $parts = explode('.', $host);
        $baseHost = implode('.', array_slice($parts, -2));
        $stmt->execute([$baseHost]);
        $empresa = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$empresa) {
        die(json_encode(['error' => "No se encontró empresa para el host: {$host}"]));
    }

    $localDsn  = 'mysql:host=' . ($empresa['bd_host'] ?: 'localhost') . ';dbname=' . $empresa['bd'];
    $localUser = $empresa['bd_user'];
    $localPass = $empresa['bd_password'];

    $pdo = new PDO($localDsn, $localUser, $localPass, [
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
    ]);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = "CREATE TABLE IF NOT EXISTS reposiciones_departamentos_excluidos (
        _id INT AUTO_INCREMENT PRIMARY KEY,
        id_reposicion INT NOT NULL,
        id_departamento INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uk_repo_depto (id_reposicion, id_departamento),
        CONSTRAINT fk_rde_reposicion FOREIGN KEY (id_reposicion)
            REFERENCES reposiciones(_id) ON DELETE CASCADE,
        CONSTRAINT fk_rde_departamento FOREIGN KEY (id_departamento)
            REFERENCES departamentos(_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    $pdo->exec($sql);

    $check = $pdo->query("SHOW TABLES LIKE 'reposiciones_departamentos_excluidos'")->fetchAll();

    echo json_encode([
        'success' => true,
        'empresa_bd' => $empresa['bd'],
        'tabla_creada' => !empty($check),
        'message' => 'Migración ejecutada. ELIMINA este archivo.',
    ]);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
}
