<?php

declare(strict_types=1);

namespace MovieStar\Tests\Support;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

/**
 * Pasta temporária da execução (no diretório temporário do sistema, nunca em img/),
 * usada para uploads de teste, sessões e logs dos processos filhos. Removida no fim.
 */
final class TempDirectory
{
    private static ?string $root = null;

    private function __construct()
    {
    }

    /**
     * Caminho de uma subpasta (criada se preciso) da pasta temporária da execução.
     */
    public static function path(string $sub = ''): string
    {
        if (self::$root === null) {
            $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'moviestar-tests-' . bin2hex(random_bytes(6));

            if (!mkdir($root, 0700, true)) {
                throw new RuntimeException('Não foi possível criar a pasta temporária ' . $root);
            }

            self::$root = $root;
        }

        $path = self::$root . ($sub !== '' ? DIRECTORY_SEPARATOR . $sub : '');

        if (!is_dir($path) && !mkdir($path, 0700, true)) {
            throw new RuntimeException('Não foi possível criar a pasta temporária ' . $path);
        }

        return $path;
    }

    /**
     * Pasta nova e vazia (ex.: uma por teste de upload).
     */
    public static function fresh(string $prefix): string
    {
        return self::path($prefix . '-' . bin2hex(random_bytes(4)));
    }

    /**
     * Apaga a pasta temporária inteira.
     */
    public static function cleanup(): void
    {
        if (self::$root !== null && is_dir(self::$root)) {
            self::removeTree(self::$root);
        }

        self::$root = null;
    }

    /**
     * Esvazia uma pasta (sem apagá-la).
     */
    public static function clear(string $dir): void
    {
        foreach (new FilesystemIterator($dir) as $item) {
            if ($item->isDir() && !$item->isLink()) {
                self::removeTree($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }
    }

    /**
     * Nomes dos arquivos de uma pasta (sem "." e "..").
     *
     * @return string[]
     */
    public static function listFiles(string $dir): array
    {
        $files = [];

        foreach (new FilesystemIterator($dir) as $item) {
            $files[] = $item->getFilename();
        }

        sort($files);

        return $files;
    }

    private static function removeTree(string $dir): void
    {
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            if ($item->isDir() && !$item->isLink()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($dir);
    }
}
