<?php

declare(strict_types=1);

namespace MovieStar\DAO;

use MovieStar\Models\User;
use PDO;

class UserDAO
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findById(int $id): ?User
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findByEmail(string $email): ?User
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->bindValue(':email', $email);
        $stmt->execute();

        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    /**
     * Insere o usuário e retorna o id gerado.
     */
    public function create(User $user): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (name, lastname, email, password, image, token, bio)
             VALUES (:name, :lastname, :email, :password, :image, :token, :bio)'
        );
        $stmt->bindValue(':name', $user->getName());
        $stmt->bindValue(':lastname', $user->getLastname());
        $stmt->bindValue(':email', $user->getEmail());
        $stmt->bindValue(':password', $user->getPassword());
        $stmt->bindValue(':image', $user->getImage());
        $stmt->bindValue(':token', $user->getToken());
        $stmt->bindValue(':bio', $user->getBio());
        $stmt->execute();

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): User
    {
        return (new User())
            ->setId((int) $row['id'])
            ->setName((string) $row['name'])
            ->setLastname((string) $row['lastname'])
            ->setEmail((string) $row['email'])
            ->setPassword((string) $row['password'])
            ->setImage($row['image'])
            ->setToken($row['token'])
            ->setBio($row['bio']);
    }
}
