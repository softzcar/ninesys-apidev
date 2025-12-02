<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\UploadedFileInterface;
use Slim\App;

return function (App $app) {
    $app->post('/upload-order-detail-image', function (Request $request, Response $response) {
        $directory = __DIR__ . '/../../public/images-orders-details';
        $uploadedFiles = $request->getUploadedFiles();

        // Create directory if it doesn't exist
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        if (empty($uploadedFiles['image'])) {
            $response->getBody()->write(json_encode(['error' => 'No image uploaded']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $uploadedFile = $uploadedFiles['image'];

        if ($uploadedFile->getError() === UPLOAD_ERR_OK) {
            $filename = moveUploadedFile($directory, $uploadedFile);

            // Get base URL from request or config
            // Assuming the API is served from the root or a known base
            // We'll construct the URL relative to the public directory
            $url = '/images-orders-details/' . $filename;

            $response->getBody()->write(json_encode([
                'success' => true,
                'url' => $url
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        }

        $response->getBody()->write(json_encode(['error' => 'Error uploading file']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    });
};

/**
 * Moves the uploaded file to the upload directory and assigns it a unique name
 * to avoid overwriting an existing uploaded file.
 *
 * @param string $directory directory to which the file is moved
 * @param UploadedFileInterface $uploadedFile file uploaded file to move
 * @return string filename of moved file
 */
function moveUploadedFile($directory, $uploadedFile)
{
    $extension = pathinfo($uploadedFile->getClientFilename(), PATHINFO_EXTENSION);
    $basename = bin2hex(random_bytes(8)); // see http://php.net/manual/en/function.random-bytes.php
    $filename = sprintf('%s.%0.8s', $basename, $extension);

    $filepath = $directory . DIRECTORY_SEPARATOR . $filename;

    // Move the file first
    $uploadedFile->moveTo($filepath);

    // Optimize the image
    optimizeImage($filepath);

    return $filename;
}

function optimizeImage($filepath)
{
    $info = getimagesize($filepath);
    if ($info === false) {
        return; // Not an image
    }

    $mime = $info['mime'];
    $image = null;

    switch ($mime) {
        case 'image/jpeg':
            $image = imagecreatefromjpeg($filepath);
            break;
        case 'image/png':
            $image = imagecreatefrompng($filepath);
            break;
        case 'image/webp':
            $image = imagecreatefromwebp($filepath);
            break;
    }

    if ($image) {
        $width = imagesx($image);
        $height = imagesy($image);
        $maxWidth = 1280;

        if ($width > $maxWidth) {
            $newWidth = $maxWidth;
            $newHeight = floor($height * ($maxWidth / $width));
            $newImage = imagecreatetruecolor($newWidth, $newHeight);

            // Preserve transparency for PNG/WEBP
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

        // Save optimized image
        switch ($mime) {
            case 'image/jpeg':
                imagejpeg($image, $filepath, 80); // 80% quality
                break;
            case 'image/png':
                // PNG compression is 0-9, 9 is max compression (lossless)
                // To reduce size we might convert to JPEG or just keep it as is but resized
                // Let's keep it as PNG but with max compression
                imagepng($image, $filepath, 9);
                break;
            case 'image/webp':
                imagewebp($image, $filepath, 80); // 80% quality
                break;
        }
        imagedestroy($image);
    }
}
