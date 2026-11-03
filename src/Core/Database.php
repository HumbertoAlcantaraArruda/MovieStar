<?php

declare(strict_types=1);

namespace MovieStar\Core;

use PDO;

/**
 * Ponto único de criação da conexão PDO (criada sob demanda, uma só vez).
 */
class Database
{
    /** @var array<string, string> */
    private array $config;

    private ?PDO $connection = null;

    /**
     * @param array<string, string> $config host, name, user, pass, charset
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function getConnection(): PDO
    {
        if ($this->connection === null) {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                $this->config['host'],
                $this->config['name'],
                $this->config['charset'] ?? 'utf8mb4'
            );

            $this->connection = new PDO($dsn, $this->config['user'], $this->config['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }

        return $this->connection;
    }
}
