<?php

declare(strict_types=1);

namespace MovieStar\Tests\Integration;

use MovieStar\Core\ImageUploader;
use MovieStar\Tests\Support\CgiClient;
use MovieStar\Tests\Support\ImageFactory;
use MovieStar\Tests\Support\TempDirectory;
use MovieStar\Tests\Support\TestCase;

/**
 * Upload de verdade (multipart via php-cgi, sem servidor web) passando pelo ImageUploader.
 * Os arquivos são gravados em uma pasta temporária, nunca em img/.
 */
class ImageUploaderUploadTest extends TestCase
{
    private const GENERATED_NAME = '/^[a-f0-9]{32}\.(jpg|png|webp)\z/';

    private string $dir;

    public function setUp(): void
    {
        $this->dir = TempDirectory::fresh('upload');
    }

    public function testAcceptsJpegPngAndWebp(): void
    {
        $images = [
            'jpg'  => ['capa.jpg', ImageFactory::jpeg(), 'image/jpeg'],
            'png'  => ['capa.png', ImageFactory::png(), 'image/png'],
            'webp' => ['capa.webp', ImageFactory::webp(), 'image/webp'],
        ];

        foreach ($images as $extension => $file) {
            $result = $this->upload($file);

            $this->assertNull($result['uploadError'] ?? null, $extension);
            $this->assertMatches(self::GENERATED_NAME, (string) $result['filename'], $extension);
            $this->assertSame($extension, pathinfo((string) $result['filename'], PATHINFO_EXTENSION), 'extensão vem do MIME real');
            $this->assertSame($file[1], file_get_contents($this->dir . DIRECTORY_SEPARATOR . $result['filename']), 'conteúdo gravado');
        }

        $this->assertCount(3, TempDirectory::listFiles($this->dir));
    }

    public function testExtensionComesFromRealMimeNotFromName(): void
    {
        // PNG enviado como "foto.php" com Content-Type mentiroso: gravado como .png.
        $result = $this->upload(['foto.php', ImageFactory::png(), 'image/jpeg']);

        $this->assertMatches('/^[a-f0-9]{32}\.png\z/', (string) ($result['filename'] ?? ''));
    }

    public function testPolyglotImageWithPhpCodeIsSavedAsImageExtension(): void
    {
        // Imagem válida com código PHP anexado: aceita como imagem, mas nunca com extensão executável.
        $result = $this->upload(['shell.php.png', ImageFactory::png() . '<?php echo "pwned"; ?>', 'image/png']);

        $this->assertMatches('/^[a-f0-9]{32}\.png\z/', (string) ($result['filename'] ?? ''));
    }

    public function testRejectsPhpDisguisedAsImage(): void
    {
        $cases = [
            ['foto.jpg', '<?php system($_GET["c"]); ?>', 'image/jpeg'],
            ['foto.png', "<?php\necho 'x';", 'image/png'],
            ['foto.gif', "GIF89a<?php echo 'x'; ?>", 'image/gif'],
        ];

        foreach ($cases as $file) {
            $result = $this->upload($file);

            $this->assertNull($result['filename'] ?? null, $file[0]);
            $this->assertNotNull($result['uploadError'] ?? null, $file[0]);
        }

        $this->assertSame([], TempDirectory::listFiles($this->dir), 'nada foi gravado');
    }

    public function testRejectsUnsupportedFormats(): void
    {
        $cases = [
            ['anim.gif', ImageFactory::gif(), 'image/gif'],
            ['img.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 'image/svg+xml'],
            ['doc.png', "%PDF-1.4\n%âãÏÓ\n1 0 obj\n<<>>\nendobj\n", 'image/png'],
        ];

        foreach ($cases as $file) {
            $this->assertSame(
                'Formato de imagem não suportado. Envie um arquivo JPG, PNG ou WEBP.',
                $this->upload($file)['uploadError'] ?? null,
                $file[0]
            );
        }
    }

    public function testRejectsCorruptImageWithImageSignature(): void
    {
        // finfo reconhece o formato pela assinatura, mas getimagesize falha ou lê dimensões zeradas.
        $cases = [
            ['truncada.jpg', "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00" . str_repeat("\x00", 30), 'image/jpeg'],
            ['zerada.png', ImageFactory::pngHeaderOnly(0, 0), 'image/png'],
        ];

        foreach ($cases as $file) {
            $this->assertSame('O arquivo enviado não é uma imagem válida.', $this->upload($file)['uploadError'] ?? null, $file[0]);
        }
    }

    public function testRejectsEmptyFile(): void
    {
        $this->assertSame('O arquivo enviado está vazio.', $this->upload(['vazio.png', '', 'image/png'])['uploadError'] ?? null);
    }

    public function testRejectsFileLargerThanLimit(): void
    {
        $png = ImageFactory::png(20, 20);

        $result = $this->upload(['grande.png', $png, 'image/png'], strlen($png) - 1);
        $this->assertStringContains('A imagem deve ter no máximo', (string) ($result['uploadError'] ?? ''), 'limite configurado no construtor');

        $result = $this->upload(['exata.png', $png, 'image/png'], strlen($png));
        $this->assertNull($result['uploadError'] ?? null, 'exatamente no limite passa');
    }

    public function testRejectsHugeDimensions(): void
    {
        $max = ImageUploader::MAX_DIMENSION;

        foreach ([[$max + 1, 10], [10, $max + 1]] as [$width, $height]) {
            $result = $this->upload(['enorme.png', ImageFactory::pngHeaderOnly($width, $height), 'image/png']);

            $this->assertSame(
                'A imagem deve ter no máximo ' . $max . ' x ' . $max . ' pixels.',
                $result['uploadError'] ?? null,
                $width . 'x' . $height
            );
        }

        $result = $this->upload(['limite.png', ImageFactory::pngHeaderOnly($max, $max), 'image/png']);
        $this->assertNull($result['uploadError'] ?? null, 'exatamente no limite passa');
    }

    public function testNoFileSelected(): void
    {
        $result = CgiClient::run('upload.php', ['uploadDir' => $this->dir], ['post' => ['x' => '1']]);

        $this->assertFalse($result['result']['wasSent']);
        $this->assertSame('Envio de arquivo inválido.', $result['result']['uploadError'] ?? null);
    }

    /**
     * @param array{0: string, 1: string, 2: string} $file
     * @return array<string, mixed> resultado do processo filho
     */
    private function upload(array $file, ?int $maxBytes = null): array
    {
        $spec = ['uploadDir' => $this->dir];

        if ($maxBytes !== null) {
            $spec['maxBytes'] = $maxBytes;
        }

        $response = CgiClient::run('upload.php', $spec, ['files' => ['image' => $file]]);
        $result   = $response['result'];

        $this->assertSame([], $result['errors'], 'sem warnings no processo filho');
        $this->assertNull($result['exception'], 'sem exceções no processo filho');
        $this->assertTrue($result['wasSent'], 'arquivo chegou como enviado');

        return $result;
    }
}
