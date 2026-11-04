<?php

declare(strict_types=1);

namespace MovieStar\Tests\Support;

use MovieStar\DAO\MovieDAO;
use MovieStar\DAO\UserDAO;
use MovieStar\Models\Movie;
use MovieStar\Models\User;
use PDO;

/**
 * Base dos testes que usam o banco de teste: cada teste começa com as tabelas vazias.
 */
abstract class DatabaseTestCase extends TestCase
{
    protected PDO $pdo;

    public function setUp(): void
    {
        $this->pdo = TestDatabase::pdo();
        TestDatabase::truncateAll();
    }

    /**
     * Cria um usuário de teste e retorna o id. A senha (texto puro) é "senha-secreta".
     */
    protected function createUser(string $email = 'tmp_test_user@example.com', string $name = 'Ana'): int
    {
        return (new UserDAO($this->pdo))->create(
            (new User())
                ->setName($name)
                ->setLastname('Teste')
                ->setEmail($email)
                ->setPassword(password_hash('senha-secreta', PASSWORD_DEFAULT))
                ->setToken(bin2hex(random_bytes(25)))
        );
    }

    protected function createMovie(int $userId, string $title = 'Filme de Teste', string $description = 'Descrição.', string $category = 'Drama'): int
    {
        return (new MovieDAO($this->pdo))->create(
            (new Movie())
                ->setTitle($title)
                ->setDescription($description)
                ->setCategory($category)
                ->setUserId($userId)
        );
    }

    protected function countRows(string $table): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
    }
}
