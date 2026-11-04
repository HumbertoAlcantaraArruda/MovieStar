<?php

declare(strict_types=1);

/**
 * Carregado pelo runner (tests/run.php) e pelos scripts filhos (tests/cgi/*.php).
 * Só funciona fora do servidor web: tests/.htaccess bloqueia o acesso HTTP e, por garantia,
 * só a CLI ou um php-cgi iniciado pelo runner (variável de ambiente MOVIESTAR_TEST_RUN, que uma
 * requisição HTTP não consegue definir) passam daqui.
 */
if (PHP_SAPI !== 'cli' && getenv('MOVIESTAR_TEST_RUN') !== '1') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/autoload.php';

// Autoload das classes de teste: namespace "MovieStar\Tests\" => pasta "tests/".
spl_autoload_register(static function (string $class): void {
    $prefix = 'MovieStar\\Tests\\';

    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';

    if (is_file($file)) {
        require $file;
    }
});
