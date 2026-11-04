<?php

declare(strict_types=1);

/**
 * Suíte de testes do MovieStar (PHP puro, sem Composer).
 *
 * Uso:   php tests/run.php [filtro] [--keep-db]
 *   filtro     roda só os testes cujo "Classe::metodo" contenha o texto (ex.: YouTube, UserDAO)
 *   --keep-db  não apaga o banco de teste no fim (para inspecionar)
 *
 * Os testes de DAO e de controller usam um banco separado (nome do banco de config/database.php
 * + "_test"), recriado a partir de database/schema.sql a cada execução e apagado no fim.
 * Código de saída: 0 se tudo passou, 1 se algo falhou.
 */

use MovieStar\Tests\Support\TempDirectory;
use MovieStar\Tests\Support\TestDatabase;
use MovieStar\Tests\Support\TestRunner;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/bootstrap.php';

error_reporting(E_ALL);
ini_set('display_errors', '1');
mb_internal_encoding('UTF-8');

// Warnings e notices viram falha do teste em que aconteceram.
set_error_handler(static function (int $severity, string $message, string $file = '', int $line = 0): bool {
    if ((error_reporting() & $severity) === 0) {
        return false;
    }

    throw new ErrorException($message, 0, $severity, $file, $line);
});

$args    = array_slice($argv, 1);
$keepDb  = in_array('--keep-db', $args, true);
$filters = array_values(array_filter($args, static function (string $arg): bool {
    return strncmp($arg, '--', 2) !== 0;
}));

$runner = new TestRunner($filters[0] ?? null);
$useDb  = $runner->needsDatabase();

echo 'MovieStar — testes automatizados (PHP ' . PHP_VERSION . ")\n";

try {
    if ($useDb) {
        TestDatabase::recreate();
        echo 'Banco de teste: ' . TestDatabase::name() . " (recriado)\n";
    }

    echo "\n";
    $exitCode = $runner->run();
} catch (Throwable $e) {
    echo "\nErro ao preparar os testes: " . get_class($e) . ': ' . $e->getMessage() . "\n";
    $exitCode = 1;
} finally {
    TempDirectory::cleanup();

    if ($useDb && !$keepDb) {
        try {
            TestDatabase::drop();
        } catch (Throwable $e) {
            echo 'Aviso: não foi possível apagar o banco de teste: ' . $e->getMessage() . "\n";
        }
    }
}

exit($exitCode);
