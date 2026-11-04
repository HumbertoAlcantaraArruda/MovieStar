<?php

declare(strict_types=1);

namespace MovieStar\Tests\Support;

/**
 * Gera bytes de imagens para os testes de upload (com GD quando disponível).
 */
final class ImageFactory
{
    private function __construct()
    {
    }

    public static function png(int $width = 4, int $height = 3): string
    {
        self::requireGd('imagepng');

        return self::render($width, $height, static function ($image): void {
            imagepng($image);
        });
    }

    public static function jpeg(int $width = 4, int $height = 3): string
    {
        self::requireGd('imagejpeg');

        return self::render($width, $height, static function ($image): void {
            imagejpeg($image);
        });
    }

    public static function webp(int $width = 4, int $height = 3): string
    {
        self::requireGd('imagewebp');

        return self::render($width, $height, static function ($image): void {
            imagewebp($image);
        });
    }

    public static function gif(): string
    {
        self::requireGd('imagegif');

        return self::render(2, 2, static function ($image): void {
            imagegif($image);
        });
    }

    /**
     * PNG mínimo montado à mão, só com o cabeçalho IHDR (sem pixels): finfo e getimagesize leem
     * as dimensões declaradas, o que permite testar dimensões enormes com poucos bytes.
     */
    public static function pngHeaderOnly(int $width, int $height): string
    {
        $ihdr = pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0);

        return "\x89PNG\r\n\x1a\n" . self::pngChunk('IHDR', $ihdr) . self::pngChunk('IEND', '');
    }

    private static function pngChunk(string $type, string $data): string
    {
        return pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
    }

    private static function requireGd(string $function): void
    {
        if (!function_exists($function)) {
            throw new SkippedTestException('extensão GD sem ' . $function . '()');
        }
    }

    private static function render(int $width, int $height, callable $writer): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 200, 30, 30));

        ob_start();
        $writer($image);
        $bytes = (string) ob_get_clean();

        // Só libera memória no PHP 7.4; no 8.0+ não faz nada e no 8.5 está obsoleta.
        if (PHP_VERSION_ID < 80000) {
            imagedestroy($image);
        }

        return $bytes;
    }
}
