<?php

declare(strict_types=1);

namespace MovieStar\Tests\Unit;

use InvalidArgumentException;
use MovieStar\Core\ImageUploader;
use MovieStar\Core\UploadException;
use MovieStar\Tests\Support\ImageFactory;
use MovieStar\Tests\Support\TempDirectory;
use MovieStar\Tests\Support\TestCase;

/**
 * Regras do ImageUploader que não dependem de um upload HTTP de verdade.
 * O fluxo completo (arquivo enviado, MIME real, dimensões, PHP disfarçado) está em
 * Integration\ImageUploaderUploadTest, que usa php-cgi.
 */
class ImageUploaderTest extends TestCase
{
    private string $dir;
    private ImageUploader $uploader;

    public function setUp(): void
    {
        $this->dir      = TempDirectory::fresh('uploader');
        $this->uploader = new ImageUploader($this->dir);
    }

    public function testConstructorRejectsMissingDirectory(): void
    {
        $this->assertThrows(InvalidArgumentException::class, function (): void {
            new ImageUploader($this->dir . DIRECTORY_SEPARATOR . 'nao-existe');
        });
    }

    public function testWasSent(): void
    {
        $this->assertFalse($this->uploader->wasSent(null));
        $this->assertFalse($this->uploader->wasSent([]));
        $this->assertFalse($this->uploader->wasSent(['error' => UPLOAD_ERR_NO_FILE]));
        $this->assertTrue($this->uploader->wasSent(['error' => UPLOAD_ERR_OK]));
        $this->assertTrue($this->uploader->wasSent(['error' => UPLOAD_ERR_INI_SIZE]), 'erro de tamanho conta como enviado');
    }

    public function testRejectsMalformedFileArrays(): void
    {
        $cases = [
            'vazio'                 => [],
            'campo name="image[]"'  => ['error' => [0], 'tmp_name' => ['x'], 'size' => [1]],
            'error como string'     => ['error' => '0', 'tmp_name' => 'x', 'size' => 1],
            'sem tmp_name'          => ['error' => 0, 'size' => 1],
        ];

        foreach ($cases as $label => $file) {
            $this->assertThrows(UploadException::class, function () use ($file): void {
                $this->uploader->upload($file);
            }, 'Envio de arquivo inválido.', $label);
        }
    }

    public function testUploadErrorCodesHaveFriendlyMessages(): void
    {
        $cases = [
            UPLOAD_ERR_INI_SIZE   => 'no máximo 2 MB',
            UPLOAD_ERR_FORM_SIZE  => 'no máximo 2 MB',
            UPLOAD_ERR_PARTIAL    => 'interrompido',
            UPLOAD_ERR_NO_FILE    => 'Selecione uma imagem.',
            UPLOAD_ERR_NO_TMP_DIR => 'Não foi possível receber a imagem.',
            UPLOAD_ERR_CANT_WRITE => 'Não foi possível receber a imagem.',
        ];

        foreach ($cases as $code => $message) {
            $this->assertThrows(UploadException::class, function () use ($code): void {
                $this->uploader->upload(['error' => $code, 'tmp_name' => '', 'size' => 0]);
            }, $message, 'código ' . $code);
        }
    }

    public function testMaxSizeMessageUsesConfiguredLimit(): void
    {
        $uploader = new ImageUploader($this->dir, 1536 * 1024);

        $this->assertThrows(UploadException::class, function () use ($uploader): void {
            $uploader->upload(['error' => UPLOAD_ERR_INI_SIZE, 'tmp_name' => '', 'size' => 0]);
        }, 'no máximo 1,5 MB');
    }

    public function testRejectsFileThatWasNotUploaded(): void
    {
        // Um caminho qualquer do servidor (ex.: forjado em $_FILES) não é aceito, mesmo sendo imagem válida.
        $path = $this->dir . DIRECTORY_SEPARATOR . 'local.png';
        file_put_contents($path, ImageFactory::png());

        $this->assertThrows(UploadException::class, function () use ($path): void {
            $this->uploader->upload(['error' => UPLOAD_ERR_OK, 'tmp_name' => $path, 'size' => filesize($path)]);
        }, 'Envio de arquivo inválido.');

        $this->assertSame(['local.png'], TempDirectory::listFiles($this->dir), 'nada foi gravado');
    }

    public function testDeleteRemovesOnlyGeneratedNames(): void
    {
        $valid = str_repeat('a1', 16) . '.png';
        $other = 'importante.png';
        file_put_contents($this->dir . DIRECTORY_SEPARATOR . $valid, 'x');
        file_put_contents($this->dir . DIRECTORY_SEPARATOR . $other, 'x');

        $this->uploader->delete(null);
        $this->uploader->delete('');
        $this->uploader->delete($other);
        $this->uploader->delete(str_repeat('a1', 16) . '.php');
        $this->uploader->delete('../' . $other);
        $this->assertSame([$valid, $other], TempDirectory::listFiles($this->dir), 'nomes fora do padrão são ignorados');

        $this->uploader->delete($valid);
        $this->assertSame([$other], TempDirectory::listFiles($this->dir));

        // Arquivo já apagado: não gera erro.
        $this->uploader->delete($valid);
    }

    public function testDeleteIgnoresPathTraversal(): void
    {
        $parent = dirname($this->dir);
        $name   = str_repeat('b2', 16) . '.jpg';
        file_put_contents($parent . DIRECTORY_SEPARATOR . $name, 'x');

        // basename() descarta o caminho: o arquivo apagado seria o de dentro da pasta (que não existe).
        $this->uploader->delete('..' . DIRECTORY_SEPARATOR . $name);
        $this->uploader->delete('../' . $name);

        $this->assertTrue(is_file($parent . DIRECTORY_SEPARATOR . $name), 'arquivo fora da pasta continua lá');
        unlink($parent . DIRECTORY_SEPARATOR . $name);
    }
}
