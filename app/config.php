<?php

const CK = "ck_3142398030de80ac909743b6b2c81cec2a23ab62";
const CS = "cs_8341cbae22ebbf4ecfe86fac9d2ff6db54042c97";
// const URL = "https://nineteengreen.com";
const URL = "https://dev.nineteengreen.com";
const URLWP = "https://";
const URLWC = "https://";

/**
 * Carga variables de entorno desde archivo .env
 * El archivo .env NO debe subirse a Git (está en .gitignore)
 */
function loadEnvFile()
{
    $envFile = __DIR__ . '/../.env';
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            // Ignorar comentarios
            if (strpos(trim($line), '#') === 0)
                continue;

            // Parsear KEY=VALUE
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                // Remover comillas si las hay
                $value = trim($value, '"\'');

                if (!empty($key)) {
                    putenv("$key=$value");
                    $_ENV[$key] = $value;
                }
            }
        }
    }
}

// Cargar variables de entorno
loadEnvFile();

// API Key de Gemini para el asistente IA (desde .env)
define('GEMINI_API_KEY', getenv('GEMINI_API_KEY') ?: '');