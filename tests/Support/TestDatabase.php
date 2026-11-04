<?php

declare(strict_types=1);

namespace MovieStar\Tests\Support;

use MovieStar\Core\Database;
use PDO;
use RuntimeException;

/**
 * Banco de teste separado: mesmas credenciais de config/database.php, com o nome do banco
 * acrescido de "_test" (ex.: moviestar_test). O banco principal nunca é tocado.
 *
 * O runner recria o banco a partir de database/schema.sql no início de cada execução e o apaga no fim.
 */
final class TestDatabase
{
    private const SUFFIX = '_test';

    /** Tabelas na ordem segura para limpar (filhas antes das mães). */
    private const TABLES = ['reviews', 'movies', 'users', 'login_attempts'];

    private static ?PDO $pdo = null;

    private function __construct()
    {
    }

    /**
     * Configuração do banco de teste (host/usuário/senha/charset de config/database.php).
     *
     * @return array<string, string>
     */
    public static function config(): array
    {
        $file = dirname(__DIR__, 2) . '/config/database.php';

        if (!is_file($file)) {
            throw new RuntimeException('config/database.php não encontrado (copie de config/database.example.php).');
        }

        $config = require $file;

        if (!is_array($config) || !isset($config['name']) || !is_string($config['name'])) {
            throw new RuntimeException('config/database.php inválido.');
        }

        $config['name'] .= self::SUFFIX;

        // O nome entra em CREATE/DROP DATABASE (identificador, sem placeholder): só caracteres seguros.
        if (preg_match('/^[A-Za-z0-9_]+' . self::SUFFIX . '\z/', $config['name']) !== 1) {
            throw new RuntimeException('Nome de banco de teste inválido: ' . $config['name']);
        }

        return $config;
    }

    public static function name(): string
    {
        return self::config()['name'];
    }

    /**
     * Conexão com o banco de teste, criada pela mesma classe da aplicação (Core\Database).
     */
    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            self::$pdo = (new Database(self::config()))->getConnection();
        }

        return self::$pdo;
    }

    /**
     * Apaga e cria de novo o banco de teste, com as tabelas de database/schema.sql.
     */
    public static function recreate(): void
    {
        $name   = self::name();
        $server = self::serverConnection();

        $server->exec('DROP DATABASE IF EXISTS `' . $name . '`');
        $server->exec('CREATE DATABASE `' . $name . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

        self::$pdo = null;
        $pdo       = self::pdo();

        foreach (self::schemaStatements() as $sql) {
            $pdo->exec($sql);
        }
    }

    public static function drop(): void
    {
        self::$pdo = null;
        self::serverConnection()->exec('DROP DATABASE IF EXISTS `' . self::name() . '`');
    }

    /**
     * Esvazia todas as tabelas (e zera os AUTO_INCREMENT) do banco de teste.
     */
    public static function truncateAll(): void
    {
        $pdo = self::pdo();

        // Confere em qual banco a conexão está antes de apagar qualquer coisa.
        if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== self::name()) {
            throw new RuntimeException('A conexão de teste não está no banco de teste.');
        }

        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

        foreach (self::TABLES as $table) {
            $pdo->exec('TRUNCATE TABLE `' . $table . '`');
        }

        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    /**
     * Conexão sem banco selecionado (para CREATE/DROP DATABASE).
     */
    private static function serverConnection(): PDO
    {
        $config = self::config();

        return new PDO(
            sprintf('mysql:host=%s;charset=%s', $config['host'], $config['charset'] ?? 'utf8mb4'),
            $config['user'],
            $config['pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }

    /**
     * Comandos de database/schema.sql, sem comentários "--", um por item.
     *
     * @return string[]
     */
    private static function schemaStatements(): array
    {
        $sql = (string) file_get_contents(dirname(__DIR__, 2) . '/database/schema.sql');
        $sql = (string) preg_replace('/^\s*--.*$/m', '', $sql);

        return array_values(array_filter(array_map('trim', explode(';', $sql)), static function (string $statement): bool {
            return $statement !== '';
        }));
    }
}
