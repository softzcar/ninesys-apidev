<?php
header('Content-Type: application/json');
echo json_encode([
    'status' => 'success',
    'message' => 'API Local Configurada Correctamente',
    'php_version' => phpversion(),
    'server_software' => $_SERVER['SERVER_SOFTWARE']
]);
