<?php

declare(strict_types=1);

/**
 * Autoload PSR-4 simples: namespace "MovieStar\" => pasta "src/".
 * Ex.: MovieStar\DAO\UserDAO => src/DAO/UserDAO.php
 */
spl_autoload_register(static function (string $class): void {
    $prefix = 'MovieStar\\';

    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = __DIR__ . '/src/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($file)) {
        require $file;
    }
});
