<?php

declare(strict_types=1);

/**
 * Processo filho (php-cgi) que recebe um arquivo enviado de verdade (multipart) e o passa ao
 * Core\ImageUploader. Iniciado apenas pelo runner via Support\CgiClient.
 *
 * Spec: uploadDir (pasta temporária de destino), maxBytes (opcional).
 */

use MovieStar\Core\ImageUploader;
use MovieStar\Core\UploadException;
use MovieStar\Tests\Support\ChildProcess;

require dirname(__DIR__) . '/bootstrap.php';

$spec     = ChildProcess::start();
$uploader = new ImageUploader(
    (string) $spec['uploadDir'],
    isset($spec['maxBytes']) ? (int) $spec['maxBytes'] : ImageUploader::DEFAULT_MAX_BYTES
);

$file = isset($_FILES['image']) && is_array($_FILES['image']) ? $_FILES['image'] : null;

ChildProcess::set('wasSent', $uploader->wasSent($file));

try {
    ChildProcess::set('filename', $uploader->upload($file ?? []));
} catch (UploadException $e) {
    ChildProcess::set('uploadError', $e->getMessage());
}

ChildProcess::set('returned', true);
