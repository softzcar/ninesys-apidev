<?php
/**
 * Migration: Añadir columna 'tipo' a departamentos y popular semillas
 * ELIMINAR ESTE ARCHIVO TRAS EJECUTAR POR SEGURIDAD
 */

define('RUNNING_MIGRATION', true);

$rootDir = realpath(__DIR__ . '/../../');

// Cargar variables de entorno
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

require_once $rootDir . '/app/lib/config.php';

// Configuración de la base de datos principal de empresas
$empresasDsn  = defined('EMPRESAS_DNS')  ? EMPRESAS_DNS  : ('mysql:host=' . (getenv('DB_HOST') ?: 'localhost') . ';dbname=' . (getenv('DB_NAME') ?: 'api_empresas'));
$empresasUser = defined('EMPRESAS_USER') ? EMPRESAS_USER : getenv('DB_USER');
$empresasPass = defined('EMPRESAS_PASS') ? EMPRESAS_PASS : getenv('DB_PASS');

$results = [];

try {
    $pdoEmpresas = new PDO($empresasDsn, $empresasUser, $empresasPass, [
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8",
    ]);
    $pdoEmpresas->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Obtener todas las empresas activas con base de datos configurada
    $stmt = $pdoEmpresas->query("SELECT _id, nombre, db_host, db_user, db_password, db_name FROM empresas WHERE activo = 1 AND db_name IS NOT NULL AND db_name != ''");
    $empresas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($empresas as $empresa) {
        $empName = $empresa['nombre'];
        $empDb = $empresa['db_name'];
        $results[$empDb] = [
            'empresa' => $empName,
            'column_added' => false,
            'seeds_updated' => false,
            'status' => 'pending',
            'error' => null
        ];

        try {
            $localDsn = 'mysql:host=' . ($empresa['db_host'] ?: 'localhost') . ';dbname=' . $empDb;
            $pdoLocal = new PDO($localDsn, $empresa['db_user'], $empresa['db_password'], [
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
            ]);
            $pdoLocal->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // 1. Verificar si la columna 'tipo' ya existe en departamentos
            $checkCol = $pdoLocal->query("SHOW COLUMNS FROM departamentos LIKE 'tipo'")->fetch();
            if (!$checkCol) {
                // Agregar la columna
                $pdoLocal->exec("ALTER TABLE departamentos ADD COLUMN tipo VARCHAR(50) NOT NULL DEFAULT 'general' COMMENT 'Tipo de comportamiento del departamento (general, corte, impresion, estampado, costura)' AFTER departamento");
                $results[$empDb]['column_added'] = true;
            } else {
                $results[$empDb]['column_added'] = 'already_exists';
            }

            // 2. Sembrar los valores por defecto
            $pdoLocal->exec("UPDATE departamentos SET tipo = 'impresion' WHERE _id = 1");
            $pdoLocal->exec("UPDATE departamentos SET tipo = 'estampado' WHERE _id = 2");
            $pdoLocal->exec("UPDATE departamentos SET tipo = 'corte' WHERE _id = 3");
            $pdoLocal->exec("UPDATE departamentos SET tipo = 'costura' WHERE _id = 4");
            
            $results[$empDb]['seeds_updated'] = true;
            $results[$empDb]['status'] = 'success';

        } catch (Exception $localEx) {
            $results[$empDb]['status'] = 'error';
            $results[$empDb]['error'] = $localEx->getMessage();
        }
    }

    echo json_encode([
        'success' => true,
        'results' => $results,
        'message' => 'Migración completada de forma global en todos los inquilinos. ELIMINA este archivo de inmediato.'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}
