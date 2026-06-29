<?php
/**
 * Script de Migración: Base64 -> Imágenes Físicas (ordenes_observaciones)
 *
 * Extrae las imágenes incrustadas en base64 dentro del HTML de
 * `ordenes_observaciones.observaciones`, las guarda como archivos físicos
 * optimizados en el MISMO directorio que usa el editor en producción
 * (public/images-orders-details/) y reemplaza el base64 por la URL de la
 * imagen, manteniendo EXACTAMENTE el mismo formato de URL que ya usan los
 * registros recientes de la empresa.
 *
 * Probado originalmente en api_emp_152 (dic 2025): 453.90 MB -> 0.74 MB.
 * Generalizado (jun 2026) para ejecutarse en cualquier empresa por parámetro.
 *
 * --------------------------------------------------------------------------
 * USO (ejecutar EN EL SERVIDOR donde vive la base y el directorio de imágenes):
 *
 *   # 1) Simulación (NO escribe nada, no crea backup, no toca la BD):
 *   php scripts/migrate_base64_images.php \
 *       --db=api_emp_194 --db-user=root --db-pass=SECRET \
 *       --base-url=https://api.nineteencustom.com --dry-run
 *
 *   # 2) Ejecución real:
 *   php scripts/migrate_base64_images.php \
 *       --db=api_emp_194 --db-user=root --db-pass=SECRET \
 *       --base-url=https://api.nineteencustom.com
 *
 * PARÁMETROS:
 *   --db          (obligatorio)  Nombre de la base de la empresa (ej: api_emp_194)
 *   --db-user     (obligatorio)  Usuario MySQL
 *   --db-pass     (obligatorio)  Clave MySQL  (también vía env DB_PASS)
 *   --db-host     (opcional)     Host MySQL. Default: localhost
 *   --base-url    (obligatorio)  Prefijo absoluto de la URL, SIN barra final.
 *                                Ej: https://api.nineteencustom.com
 *                                La URL final será:
 *                                <base-url>/images-orders-details/<archivo>
 *   --batch       (opcional)     Tamaño de lote. Default: 20
 *   --dry-run     (opcional)     Simula: cuenta filas/imágenes y estima MB,
 *                                NO crea archivos, NO crea backup, NO toca la BD.
 *   --no-backup   (opcional)     Omite la creación de la tabla de respaldo
 *                                (NO recomendado).
 *
 * NOTA: el directorio de salida SIEMPRE es public/images-orders-details/
 *       relativo a este script (mismo que usa el endpoint /upload-order-detail-image).
 *       No se inventa ninguna ruta nueva.
 * --------------------------------------------------------------------------
 */

// ---------------------------------------------------------------------------
// Parseo de argumentos
// ---------------------------------------------------------------------------
$opts = getopt('', [
    'db:', 'db-user:', 'db-pass::', 'db-host::',
    'base-url:', 'batch::', 'dry-run', 'no-backup', 'help'
]);

if (isset($opts['help'])) {
    // Muestra la cabecera del archivo como ayuda
    echo file_get_contents(__FILE__, false, null, 0, 2400), "\n";
    exit(0);
}

$DRY_RUN   = isset($opts['dry-run']);
$NO_BACKUP = isset($opts['no-backup']);

$dbConfig = [
    'host'   => $opts['db-host'] ?? 'localhost',
    'dbname' => $opts['db'] ?? null,
    'user'   => $opts['db-user'] ?? null,
    'pass'   => $opts['db-pass'] ?? (getenv('DB_PASS') ?: null),
];

$BASE_URL = isset($opts['base-url']) ? rtrim($opts['base-url'], '/') : null;

// Configuración fija (idéntica al endpoint de subida en producción)
define('BATCH_SIZE', isset($opts['batch']) ? max(1, (int) $opts['batch']) : 20);
define('MAX_WIDTH', 1280);
define('JPEG_QUALITY', 80);
define('OUTPUT_DIR', __DIR__ . '/../public/images-orders-details');
define('URL_DIR_SEGMENT', '/images-orders-details/');

ini_set('memory_limit', '512M');
set_time_limit(0);

// ---------------------------------------------------------------------------
// Utilidades de salida
// ---------------------------------------------------------------------------
function colorLog($message, $type = 'info')
{
    $colors = [
        'info' => "\033[34m", 'success' => "\033[32m", 'warning' => "\033[33m",
        'error' => "\033[31m", 'reset' => "\033[0m",
    ];
    $prefixes = [
        'info' => '[INFO]', 'success' => '[OK]', 'warning' => '[WARN]',
        'error' => '[ERROR]',
    ];
    $prefix = $prefixes[$type] ?? '[LOG]';
    echo ($colors[$type] ?? '') . $prefix . " " . $message . $colors['reset'] . "\n";
}

function fail($msg)
{
    colorLog($msg, 'error');
    exit(1);
}

// ---------------------------------------------------------------------------
// Validación de parámetros
// ---------------------------------------------------------------------------
if (empty($dbConfig['dbname']))  fail("Falta --db (nombre de la base, ej: api_emp_194).");
if (empty($dbConfig['user']))    fail("Falta --db-user.");
if (empty($dbConfig['pass']))    fail("Falta --db-pass (o variable de entorno DB_PASS).");
if (empty($BASE_URL))            fail("Falta --base-url (ej: https://api.nineteencustom.com).");

// ---------------------------------------------------------------------------
// Conexión
// ---------------------------------------------------------------------------
function getConnection($config)
{
    try {
        $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4";
        return new PDO($dsn, $config['user'], $config['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (PDOException $e) {
        fail("Error de conexión: " . $e->getMessage());
    }
}

// ---------------------------------------------------------------------------
// Backup
// ---------------------------------------------------------------------------
function createBackup($pdo)
{
    colorLog("Creando backup de la tabla...", 'info');
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'ordenes_observaciones_backup_base64'");
        if ($stmt->rowCount() > 0) {
            colorLog("Ya existe 'ordenes_observaciones_backup_base64'. ¿Continuar SIN crear nuevo backup? (s/n)", 'warning');
            if (strtolower(trim(fgets(STDIN))) !== 's') {
                fail("Abortado por el usuario.");
            }
            return true;
        }
        $pdo->exec("CREATE TABLE ordenes_observaciones_backup_base64 AS SELECT * FROM ordenes_observaciones");
        $count = $pdo->query("SELECT COUNT(*) FROM ordenes_observaciones_backup_base64")->fetchColumn();
        colorLog("Backup creado con {$count} registros.", 'success');
        return true;
    } catch (PDOException $e) {
        fail("Error creando backup: " . $e->getMessage());
    }
}

// ---------------------------------------------------------------------------
// Consultas
// ---------------------------------------------------------------------------
function getTotalRecords($pdo)
{
    return (int) $pdo->query(
        "SELECT COUNT(*) FROM ordenes_observaciones WHERE observaciones LIKE '%data:image%base64%'"
    )->fetchColumn();
}

function getRecordsWithBase64($pdo, $limit)
{
    // offset siempre 0: tras actualizar, las filas migradas dejan de cumplir el WHERE
    $stmt = $pdo->prepare("
        SELECT _id, id_orden, observaciones
        FROM ordenes_observaciones
        WHERE observaciones LIKE '%data:image%base64%'
        ORDER BY _id ASC
        LIMIT :limit
    ");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

// ---------------------------------------------------------------------------
// Extracción / guardado de imágenes
// ---------------------------------------------------------------------------
function extractBase64Images($html)
{
    $pattern = '/data:image\/(jpeg|jpg|png|gif|webp);base64,([A-Za-z0-9+\/=]+)/';
    preg_match_all($pattern, $html, $matches, PREG_SET_ORDER);
    return $matches;
}

function generateFilename($extension)
{
    return sprintf('%s.%s', bin2hex(random_bytes(8)), $extension);
}

function saveImageFromBase64($base64Data, $mimeType)
{
    $mimeType = strtolower($mimeType);
    if ($mimeType === 'jpg') $mimeType = 'jpeg';

    $imageData = base64_decode($base64Data);
    if ($imageData === false) {
        throw new Exception("No se pudo decodificar base64");
    }

    switch ($mimeType) {
        case 'jpeg':
        case 'jpg':  $extension = 'jpg';  break;
        case 'png':  $extension = 'png';  break;
        case 'gif':  $extension = 'gif';  break;
        case 'webp': $extension = 'webp'; break;
        default:     $extension = 'jpg';  break;
    }

    $filename = generateFilename($extension);
    $filepath = OUTPUT_DIR . DIRECTORY_SEPARATOR . $filename;

    if (file_put_contents($filepath, $imageData) === false) {
        throw new Exception("No se pudo guardar el archivo: {$filepath}");
    }

    optimizeImage($filepath, $mimeType);
    return $filename;
}

function optimizeImage($filepath, $mimeType = null)
{
    $info = @getimagesize($filepath);
    if ($info === false) return;

    $mime = $mimeType ? "image/{$mimeType}" : $info['mime'];
    switch ($mime) {
        case 'image/jpeg':
        case 'image/jpg':  $image = @imagecreatefromjpeg($filepath); break;
        case 'image/png':  $image = @imagecreatefrompng($filepath);  break;
        case 'image/gif':  $image = @imagecreatefromgif($filepath);  break;
        case 'image/webp': $image = @imagecreatefromwebp($filepath); break;
        default:           $image = null; break;
    }
    if (!$image) return;

    $width  = imagesx($image);
    $height = imagesy($image);

    if ($width > MAX_WIDTH) {
        $newWidth  = MAX_WIDTH;
        $newHeight = (int) floor($height * (MAX_WIDTH / $width));
        $newImage  = imagecreatetruecolor($newWidth, $newHeight);
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

    switch ($mime) {
        case 'image/jpeg':
        case 'image/jpg':  imagejpeg($image, $filepath, JPEG_QUALITY); break;
        case 'image/png':  imagepng($image, $filepath, 9); break;
        case 'image/gif':  imagegif($image, $filepath); break;
        case 'image/webp': imagewebp($image, $filepath, JPEG_QUALITY); break;
    }
    imagedestroy($image);
}

function updateRecord($pdo, $id, $newHtml)
{
    $stmt = $pdo->prepare("UPDATE ordenes_observaciones SET observaciones = :html WHERE _id = :id");
    return $stmt->execute([':html' => $newHtml, ':id' => $id]);
}

// ---------------------------------------------------------------------------
// Procesamiento de un registro
// ---------------------------------------------------------------------------
function processRecord($pdo, $record)
{
    global $DRY_RUN, $BASE_URL;

    $html   = $record['observaciones'];
    $images = extractBase64Images($html);
    if (empty($images)) {
        return ['processed' => 0, 'errors' => 0, 'bytes' => 0];
    }

    $processed = 0;
    $errors    = 0;
    $bytes     = 0;

    foreach ($images as $match) {
        $fullMatch  = $match[0];   // data:image/xxx;base64,XXXX
        $mimeType   = $match[1];
        $base64Data = $match[2];
        $bytes     += strlen($fullMatch);

        if ($DRY_RUN) {
            $processed++;
            continue;
        }

        try {
            $filename = saveImageFromBase64($base64Data, $mimeType);
            // URL ABSOLUTA, idéntica al formato de los registros recientes:
            //   https://api.nineteencustom.com/images-orders-details/<archivo>
            $url  = $BASE_URL . URL_DIR_SEGMENT . $filename;
            $html = str_replace($fullMatch, $url, $html);
            $processed++;
        } catch (Exception $e) {
            colorLog("  Error en imagen del registro {$record['_id']}: " . $e->getMessage(), 'warning');
            $errors++;
        }
    }

    if (!$DRY_RUN && $processed > 0) {
        updateRecord($pdo, $record['_id'], $html);
    }

    return ['processed' => $processed, 'errors' => $errors, 'bytes' => $bytes];
}

// ---------------------------------------------------------------------------
// Main
// ---------------------------------------------------------------------------
function main()
{
    global $dbConfig, $DRY_RUN, $NO_BACKUP, $BASE_URL;

    echo "\n";
    colorLog("==============================================", 'info');
    colorLog("  MIGRACIÓN BASE64 -> IMÁGENES FÍSICAS" . ($DRY_RUN ? "  [DRY-RUN]" : ""), 'info');
    colorLog("==============================================", 'info');
    colorLog("Base de datos : {$dbConfig['dbname']} @ {$dbConfig['host']}", 'info');
    colorLog("URL base      : {$BASE_URL}" . URL_DIR_SEGMENT, 'info');
    colorLog("Directorio    : " . OUTPUT_DIR, 'info');
    echo "\n";

    $pdo = getConnection($dbConfig);
    colorLog("Conexión establecida.", 'success');

    $total = getTotalRecords($pdo);
    colorLog("Registros con base64: {$total}", 'info');
    if ($total === 0) {
        colorLog("No hay nada que migrar.", 'success');
        exit(0);
    }

    // -------- DRY-RUN: solo medir vía SQL, sin cargar HTML ni tocar nada --------
    if ($DRY_RUN) {
        // Nº de imágenes y bytes base64 calculados en el motor (sin traer datos a PHP)
        $row = $pdo->query("
            SELECT
              COALESCE(SUM( (LENGTH(observaciones)-LENGTH(REPLACE(observaciones,'data:image',''))) / LENGTH('data:image') ),0) AS imgs,
              COALESCE(SUM( LENGTH(observaciones) - LENGTH(REGEXP_REPLACE(observaciones,'data:image[^\"'')]*','')) ),0) AS bytes
            FROM ordenes_observaciones
            WHERE observaciones LIKE '%data:image%base64%'
        ")->fetch();
        $imgs  = (int) round($row['imgs']);
        $bytes = (int) $row['bytes'];
        echo "\n";
        colorLog("---- SIMULACIÓN (no se escribió nada) ----", 'warning');
        colorLog("Filas con base64        : {$total}", 'info');
        colorLog("Imágenes a extraer      : {$imgs}", 'info');
        colorLog("Peso base64 a liberar   : " . round($bytes / 1048576, 2) . " MB", 'info');
        colorLog("Directorio destino      : " . OUTPUT_DIR . (is_dir(OUTPUT_DIR) ? " (existe)" : " (se crearía)"), 'info');
        colorLog("Formato URL resultante  : {$BASE_URL}" . URL_DIR_SEGMENT . "<archivo>", 'info');
        echo "\n";
        colorLog("Para ejecutar de verdad, repite el comando SIN --dry-run.", 'success');
        exit(0);
    }

    // -------- Ejecución real --------
    if (!is_dir(OUTPUT_DIR) && !mkdir(OUTPUT_DIR, 0755, true)) {
        fail("No se pudo crear el directorio: " . OUTPUT_DIR);
    }

    if (!$NO_BACKUP) {
        createBackup($pdo);
    } else {
        colorLog("ADVERTENCIA: se omitió el backup (--no-backup).", 'warning');
    }

    $totalProcessed = 0;
    $totalErrors    = 0;
    $batchNumber    = 0;
    $startTime      = time();

    colorLog("Procesando en lotes de " . BATCH_SIZE . "...", 'info');
    echo "\n";

    while (true) {
        $records = getRecordsWithBase64($pdo, BATCH_SIZE);
        if (empty($records)) break;

        $batchNumber++;
        colorLog("Lote {$batchNumber}: " . count($records) . " registros...", 'info');

        foreach ($records as $record) {
            $result = processRecord($pdo, $record);
            $totalProcessed += $result['processed'];
            $totalErrors    += $result['errors'];
            if ($result['processed'] > 0) {
                colorLog("  #{$record['_id']} (orden {$record['id_orden']}): {$result['processed']} imágenes", 'success');
            }
        }

        $remaining = getTotalRecords($pdo);
        $elapsed   = time() - $startTime;
        colorLog("Progreso: faltan {$remaining} filas | {$totalProcessed} imágenes | {$elapsed}s", 'info');
        echo "\n";

        if ($remaining === 0) break;
        gc_collect_cycles();
    }

    $duration = time() - $startTime;
    echo "\n";
    colorLog("==============================================", 'info');
    colorLog("  MIGRACIÓN COMPLETADA", 'success');
    colorLog("==============================================", 'info');
    colorLog("Imágenes procesadas : {$totalProcessed}", 'success');
    colorLog("Errores             : {$totalErrors}", $totalErrors > 0 ? 'warning' : 'success');
    colorLog("Tiempo total        : {$duration} s", 'info');

    $remainingBase64 = getTotalRecords($pdo);
    if ($remainingBase64 === 0) {
        colorLog("✓ No quedan imágenes base64 en la tabla.", 'success');
    } else {
        colorLog("⚠ Aún quedan {$remainingBase64} registros con base64.", 'warning');
    }
    echo "\n";
}

main();
