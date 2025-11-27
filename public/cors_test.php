<?php
// cors_test.php - Prueba de aislamiento para CORS y PHP
// Sube este archivo a la carpeta public/ de tu servidor remoto

// Intentar configurar encabezados CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization, X-ID-Empresa");
header("Content-Type: application/json");

// Manejar solicitud preflight (OPTIONS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Respuesta exitosa
echo json_encode([
    "status" => "success",
    "message" => "CORS y PHP funcionan correctamente en este archivo aislado.",
    "server_software" => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    "php_version" => phpversion()
]);
