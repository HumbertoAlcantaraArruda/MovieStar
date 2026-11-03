<?php

declare(strict_types=1);

namespace MovieStar\Core;

use finfo;
use InvalidArgumentException;

/**
 * Recebe imagens enviadas por formulário e as grava em uma pasta com nome aleatório.
 *
 * A extensão do arquivo salvo é sempre derivada do MIME real (finfo), nunca do nome enviado.
 */
class ImageUploader
{
    public const DEFAULT_MAX_BYTES = 2 * 1024 * 1024; // 2 MB

    /** MIME aceito => extensão gravada. */
    private const ALLOWED_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    /** Formato dos nomes gerados por esta classe (32 hex + extensão permitida). */
    private const FILENAME_PATTERN = '/^[a-f0-9]{32}\.(jpg|png|webp)$/';

    private string $directory;
    private int $maxBytes;

    public function __construct(string $directory, int $maxBytes = self::DEFAULT_MAX_BYTES)
    {
        $realDirectory = realpath($directory);

        if ($realDirectory === false || !is_dir($realDirectory) || !is_writable($realDirectory)) {
            throw new InvalidArgumentException('Pasta de upload inexistente ou sem permissão de escrita: ' . $directory);
        }

        $this->directory = $realDirectory;
        $this->maxBytes  = $maxBytes;
    }

    /**
     * Indica se o campo de arquivo foi preenchido (o usuário escolheu algum arquivo).
     *
     * @param array<string, mixed>|null $file item de $_FILES
     */
    public function wasSent(?array $file): bool
    {
        return $file !== null
            && isset($file['error'])
            && $file['error'] !== UPLOAD_ERR_NO_FILE;
    }

    /**
     * Valida e grava a imagem, retornando o nome do arquivo salvo.
     *
     * @param array<string, mixed> $file item de $_FILES
     * @throws UploadException
     */
    public function upload(array $file): string
    {
        // Campos com name="x[]" chegam como arrays: não são aceitos aqui.
        if (!isset($file['error'], $file['tmp_name'], $file['size'])
            || !is_int($file['error'])
            || !is_string($file['tmp_name'])) {
            throw new UploadException('Envio de arquivo inválido.');
        }

        $this->assertNoUploadError($file['error']);

        if (!is_uploaded_file($file['tmp_name'])) {
            throw new UploadException('Envio de arquivo inválido.');
        }

        $size = (int) filesize($file['tmp_name']);

        if ($size <= 0) {
            throw new UploadException('O arquivo enviado está vazio.');
        }

        if ($size > $this->maxBytes) {
            throw new UploadException('A imagem deve ter no máximo ' . $this->formatMaxSize() . '.');
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);

        if (!is_string($mime) || !isset(self::ALLOWED_TYPES[$mime])) {
            throw new UploadException('Formato de imagem não suportado. Envie um arquivo JPG, PNG ou WEBP.');
        }

        $imageInfo = @getimagesize($file['tmp_name']);

        if ($imageInfo === false
            || $imageInfo[0] <= 0
            || $imageInfo[1] <= 0
            || ($imageInfo['mime'] ?? '') !== $mime) {
            throw new UploadException('O arquivo enviado não é uma imagem válida.');
        }

        $filename = bin2hex(random_bytes(16)) . '.' . self::ALLOWED_TYPES[$mime];

        if (!move_uploaded_file($file['tmp_name'], $this->directory . DIRECTORY_SEPARATOR . $filename)) {
            throw new UploadException('Não foi possível salvar a imagem. Tente novamente.');
        }

        return $filename;
    }

    /**
     * Apaga um arquivo gerado por esta classe. Nomes fora do padrão (ou com caminho) são ignorados.
     */
    public function delete(?string $filename): void
    {
        if ($filename === null || $filename === '') {
            return;
        }

        $filename = basename($filename);

        if (preg_match(self::FILENAME_PATTERN, $filename) !== 1) {
            return;
        }

        $path = $this->directory . DIRECTORY_SEPARATOR . $filename;

        if (is_file($path)) {
            unlink($path);
        }
    }

    /**
     * @throws UploadException
     */
    private function assertNoUploadError(int $error): void
    {
        switch ($error) {
            case UPLOAD_ERR_OK:
                return;
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                throw new UploadException('A imagem deve ter no máximo ' . $this->formatMaxSize() . '.');
            case UPLOAD_ERR_PARTIAL:
                throw new UploadException('O envio da imagem foi interrompido. Tente novamente.');
            case UPLOAD_ERR_NO_FILE:
                throw new UploadException('Selecione uma imagem.');
            default:
                throw new UploadException('Não foi possível receber a imagem. Tente novamente.');
        }
    }

    private function formatMaxSize(): string
    {
        $mb = (float) ($this->maxBytes / (1024 * 1024));

        return ($mb === floor($mb) ? (string) (int) $mb : number_format($mb, 1, ',', '')) . ' MB';
    }
}
