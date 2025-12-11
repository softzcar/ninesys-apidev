<?php
/**
 * Script de Migración: Base64 → Imágenes Físicas
 * 
 * Este script extrae imágenes base64 de la tabla ordenes_observaciones,
 * las convierte en archivos físicos optimizados, y actualiza los registros
 * con las URLs de las imágenes.
 * 
 * Uso: php scripts/migrate_base64_images.php
 */

// Configuración
define('BATCH_SIZE', 20);
define('MAX_WIDTH', 1280);
define('JPEG_QUALITY', 80);
define('OUTPUT_DIR', __DIR__ . '/../public/images-orders-details');
define('URL_PREFIX', '/images-orders-details/');

// Configuración de base de datos
$dbConfig = [
    'host' => 'localhost',
    'dbname' => 'api_emp_152',
    'user' => 'api_user_152',
    'pass' => 'cf747993a6231d6e0a15f731'
];

// Configuración de memoria y tiempo
ini_set('memory_limit', '512M');
set_time_limit(0);

// Colores para output
function colorLog($message, $type = 'info')
{
    $colors = [
        'info' => "\033[34m",    // Azul
        'success' => "\033[32m", // Verde
        'warning' => "\033[33m", // Amarillo
        'error' => "\033[31m",   // Rojo
        'reset' => "\033[0m"
    ];

    $prefix = match ($type) {
        'info' => '[INFO]',
        'success' => '[OK]',
        'warning' => '[WARN]',
        'error' => '[ERROR]',
        default => '[LOG]'
    };

    echo $colors[$type] . $prefix . " " . $message . $colors['reset'] . "\n";
}

// Conectar a la base de datos
function getConnection($config)
{
    try {
        $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4";
        $pdo = new PDO($dsn, $config['user'], $config['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        return $pdo;
    } catch (PDOException $e) {
        colorLog("Error de conexión: " . $e->getMessage(), 'error');
        exit(1);
    }
}

// Crear backup de la tabla
function createBackup($pdo)
{
    colorLog("Creando backup de la tabla...", 'info');

    try {
        // Verificar si el backup ya existe
        $stmt = $pdo->query("SHOW TABLES LIKE 'ordenes_observaciones_backup_base64'");
        if ($stmt->rowCount() > 0) {
            colorLog("El backup ya existe. ¿Desea continuar sin crear nuevo backup? (s/n)", 'warning');
            $input = trim(fgets(STDIN));
            if (strtolower($input) !== 's') {
                colorLog("Abortando...", 'error');
                exit(0);
            }
            return true;
        }

        // Crear backup
        $pdo->exec("CREATE TABLE ordenes_observaciones_backup_base64 AS SELECT * FROM ordenes_observaciones");

        // Verificar backup
        $count = $pdo->query("SELECT COUNT(*) FROM ordenes_observaciones_backup_base64")->fetchColumn();
        colorLog("Backup creado exitosamente con {$count} registros", 'success');

        return true;
    } catch (PDOException $e) {
        colorLog("Error creando backup: " . $e->getMessage(), 'error');
        return false;
    }
}

// Obtener total de registros con base64
function getTotalRecords($pdo)
{
    $stmt = $pdo->query("SELECT COUNT(*) FROM ordenes_observaciones WHERE observaciones LIKE '%data:image%base64%'");
    return (int) $stmt->fetchColumn();
}

// Obtener registros con base64 (paginado)
function getRecordsWithBase64($pdo, $offset, $limit)
{
    $stmt = $pdo->prepare("
        SELECT _id, id_orden, observaciones 
        FROM ordenes_observaciones 
        WHERE observaciones LIKE '%data:image%base64%' 
        ORDER BY _id ASC 
        LIMIT :offset, :limit
    ");
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

// Extraer imágenes base64 del HTML
function extractBase64Images($html)
{
    $pattern = '/data:image\/(jpeg|jpg|png|gif|webp);base64,([A-Za-z0-9+\/=]+)/';
    preg_match_all($pattern, $html, $matches, PREG_SET_ORDER);
    return $matches;
}

// Generar nombre único para archivo
function generateFilename($extension)
{
    $basename = bin2hex(random_bytes(8));
    return sprintf('%s.%s', $basename, $extension);
}

// Guardar imagen desde base64
function saveImageFromBase64($base64Data, $mimeType)
{
    // Normalizar tipo mime
    $mimeType = strtolower($mimeType);
    if ($mimeType === 'jpg')
        $mimeType = 'jpeg';

    // Decodificar base64
    $imageData = base64_decode($base64Data);
    if ($imageData === false) {
        throw new Exception("No se pudo decodificar base64");
    }

    // Determinar extensión
    $extension = match ($mimeType) {
        'jpeg', 'jpg' => 'jpg',
        'png' => 'png',
        'gif' => 'gif',
        'webp' => 'webp',
        default => 'jpg'
    };

    // Generar nombre y ruta
    $filename = generateFilename($extension);
    $filepath = OUTPUT_DIR . DIRECTORY_SEPARATOR . $filename;

    // Guardar archivo
    if (file_put_contents($filepath, $imageData) === false) {
        throw new Exception("No se pudo guardar el archivo: {$filepath}");
    }

    // Optimizar imagen
    optimizeImage($filepath, $mimeType);

    return $filename;
}

// Optimizar imagen (redimensionar y comprimir)
function optimizeImage($filepath, $mimeType = null)
{
    $info = @getimagesize($filepath);
    if ($info === false) {
        return; // No es una imagen válida
    }

    $mime = $mimeType ? "image/{$mimeType}" : $info['mime'];
    $image = null;

    switch ($mime) {
        case 'image/jpeg':
        case 'image/jpg':
            $image = @imagecreatefromjpeg($filepath);
            break;
        case 'image/png':
            $image = @imagecreatefrompng($filepath);
            break;
        case 'image/gif':
            $image = @imagecreatefromgif($filepath);
            break;
        case 'image/webp':
            $image = @imagecreatefromwebp($filepath);
            break;
    }

    if (!$image) {
        return;
    }

    $width = imagesx($image);
    $height = imagesy($image);

    // Redimensionar si es necesario
    if ($width > MAX_WIDTH) {
        $newWidth = MAX_WIDTH;
        $newHeight = (int) floor($height * (MAX_WIDTH / $width));
        $newImage = imagecreatetruecolor($newWidth, $newHeight);

        // Preservar transparencia para PNG/WEBP
        if ($mime == 'image/png' || $mime == 'image/webp') {
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);
            $transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
            imagefilledrectangle($newImage, 0, 0, $newWidth, $newHeight, $transparent);
        }

        imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($image);
        $image = $newImage;
    }

    // Guardar imagen optimizada
    switch ($mime) {
        case 'image/jpeg':
        case 'image/jpg':
            imagejpeg($image, $filepath, JPEG_QUALITY);
            break;
        case 'image/png':
            imagepng($image, $filepath, 9);
            break;
        case 'image/gif':
            imagegif($image, $filepath);
            break;
        case 'image/webp':
            imagewebp($image, $filepath, JPEG_QUALITY);
            break;
    }

    imagedestroy($image);
}

// Actualizar registro en la base de datos
function updateRecord($pdo, $id, $newHtml)
{
    $stmt = $pdo->prepare("UPDATE ordenes_observaciones SET observaciones = :html WHERE _id = :id");
    return $stmt->execute([':html' => $newHtml, ':id' => $id]);
}

// Procesar un registro
function processRecord($pdo, $record)
{
    $html = $record['observaciones'];
    $images = extractBase64Images($html);

    if (empty($images)) {
        return ['processed' => 0, 'errors' => 0];
    }

    $processed = 0;
    $errors = 0;

    foreach ($images as $match) {
        $fullMatch = $match[0]; // data:image/xxx;base64,XXXX
        $mimeType = $match[1];  // jpeg, png, etc
        $base64Data = $match[2]; // datos base64

        try {
            // Guardar imagen y obtener nombre de archivo
            $filename = saveImageFromBase64($base64Data, $mimeType);

            // Construir URL completa
            $url = URL_PREFIX . $filename;

            // Reemplazar en el HTML
            $html = str_replace($fullMatch, $url, $html);

            $processed++;
        } catch (Exception $e) {
            colorLog("  Error procesando imagen en registro {$record['_id']}: " . $e->getMessage(), 'warning');
            $errors++;
        }
    }

    // Actualizar registro si hubo cambios
    if ($processed > 0) {
        updateRecord($pdo, $record['_id'], $html);
    }

    return ['processed' => $processed, 'errors' => $errors];
}

// Función principal
function main()
{
    global $dbConfig;

    echo "\n";
    colorLog("==============================================", 'info');
    colorLog("  MIGRACIÓN BASE64 → IMÁGENES FÍSICAS", 'info');
    colorLog("==============================================", 'info');
    echo "\n";

    // Verificar/crear directorio de salida
    if (!is_dir(OUTPUT_DIR)) {
        if (!mkdir(OUTPUT_DIR, 0755, true)) {
            colorLog("No se pudo crear el directorio: " . OUTPUT_DIR, 'error');
            exit(1);
        }
        colorLog("Directorio creado: " . OUTPUT_DIR, 'success');
    }

    // Conectar a la base de datos
    colorLog("Conectando a la base de datos...", 'info');
    $pdo = getConnection($dbConfig);
    colorLog("Conexión establecida", 'success');

    // Crear backup
    if (!createBackup($pdo)) {
        exit(1);
    }

    // Obtener total de registros
    $total = getTotalRecords($pdo);
    colorLog("Registros con base64: {$total}", 'info');

    if ($total === 0) {
        colorLog("No hay registros para procesar", 'success');
        exit(0);
    }

    // Procesar en lotes
    $offset = 0;
    $totalProcessed = 0;
    $totalErrors = 0;
    $batchNumber = 0;
    $startTime = time();

    colorLog("Iniciando procesamiento en lotes de " . BATCH_SIZE . "...", 'info');
    echo "\n";

    while ($offset < $total) {
        $batchNumber++;
        $records = getRecordsWithBase64($pdo, 0, BATCH_SIZE); // Siempre offset 0 porque actualizamos

        if (empty($records)) {
            break; // No hay más registros con base64
        }

        colorLog("Lote {$batchNumber}: Procesando " . count($records) . " registros...", 'info');

        foreach ($records as $record) {
            $result = processRecord($pdo, $record);
            $totalProcessed += $result['processed'];
            $totalErrors += $result['errors'];

            if ($result['processed'] > 0) {
                colorLog("  Registro #{$record['_id']} (orden {$record['id_orden']}): {$result['processed']} imágenes migradas", 'success');
            }
        }

        // Verificar cuántos quedan
        $remaining = getTotalRecords($pdo);
        $elapsed = time() - $startTime;
        $processedRecords = $total - $remaining;

        colorLog("Progreso: {$processedRecords}/{$total} registros | {$totalProcessed} imágenes | Tiempo: {$elapsed}s", 'info');
        echo "\n";

        if ($remaining === 0) {
            break;
        }

        // Liberar memoria
        gc_collect_cycles();
    }

    // Resumen final
    $endTime = time();
    $duration = $endTime - $startTime;

    echo "\n";
    colorLog("==============================================", 'info');
    colorLog("  MIGRACIÓN COMPLETADA", 'success');
    colorLog("==============================================", 'info');
    colorLog("Imágenes procesadas: {$totalProcessed}", 'success');
    colorLog("Errores: {$totalErrors}", $totalErrors > 0 ? 'warning' : 'success');
    colorLog("Tiempo total: {$duration} segundos", 'info');

    // Verificar resultado final
    $remainingBase64 = getTotalRecords($pdo);
    if ($remainingBase64 === 0) {
        colorLog("✓ No quedan imágenes base64 en la tabla", 'success');
    } else {
        colorLog("⚠ Aún quedan {$remainingBase64} registros con base64", 'warning');
    }

    echo "\n";
}

// Ejecutar
main();
