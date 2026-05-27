<?php
/**
 * Migration: crear tabla reposiciones_departamentos_excluidos
 * ELIMINAR ESTE ARCHIVO TRAS EJECUTAR
 */

require_once __DIR__ . '/../../app/lib/config.php';
require_once __DIR__ . '/../../app/model/LocalDB.php';

// Carga constantes del entorno del servidor
$dsn  = defined('LOCAL_DNS')  ? LOCAL_DNS  : null;
$user = defined('LOCAL_USER') ? LOCAL_USER : null;
$pass = defined('LOCAL_PASS') ? LOCAL_PASS : null;

if (!$dsn) {
    // Intentar leer desde getenv (si el server las expone)
    $dsn  = getenv('LOCAL_DNS');
    $user = getenv('LOCAL_USER');
    $pass = getenv('LOCAL_PASS');
}

if (!$dsn) {
    die(json_encode(['error' => 'No se pudo obtener la configuracion de BD']));
}

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8",
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

    // Verificar que se creó
    $check = $pdo->query("SHOW TABLES LIKE 'reposiciones_departamentos_excluidos'")->fetchAll();

    echo json_encode([
        'success' => true,
        'tabla_creada' => !empty($check),
        'tabla' => 'reposiciones_departamentos_excluidos',
        'message' => 'Migración ejecutada correctamente. ELIMINA este archivo.'
    ]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
