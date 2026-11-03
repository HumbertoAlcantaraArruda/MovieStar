<?php

declare(strict_types=1);

namespace MovieStar\DAO;

use MovieStar\Models\Movie;
use PDO;
use PDOStatement;

class MovieDAO
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Insere o filme e retorna o id gerado.
     */
    public function create(Movie $movie): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO movies (title, description, image, trailer, category, length, users_id)
             VALUES (:title, :description, :image, :trailer, :category, :length, :users_id)'
        );
        $this->bindMovie($stmt, $movie);
        $stmt->execute();

        return (int) $this->pdo->lastInsertId();
    }

    public function update(Movie $movie): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE movies
                SET title = :title, description = :description, image = :image, trailer = :trailer,
                    category = :category, length = :length, users_id = :users_id
              WHERE id = :id'
        );
        $this->bindMovie($stmt, $movie);
        $stmt->bindValue(':id', (int) $movie->getId(), PDO::PARAM_INT);
        $stmt->execute();
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM movies WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function findById(int $id): ?Movie
    {
        $stmt = $this->pdo->prepare('SELECT * FROM movies WHERE id = :id LIMIT 1');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    /**
     * @return Movie[]
     */
    public function findLatest(int $limit): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM movies ORDER BY created_at DESC, id DESC LIMIT :limit');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $this->hydrateAll($stmt);
    }

    /**
     * @return Movie[]
     */
    public function findByCategory(string $category, int $limit): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM movies WHERE category = :category ORDER BY created_at DESC, id DESC LIMIT :limit'
        );
        $stmt->bindValue(':category', $category);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $this->hydrateAll($stmt);
    }

    /**
     * @return Movie[]
     */
    public function findByUser(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM movies WHERE users_id = :users_id ORDER BY created_at DESC, id DESC');
        $stmt->bindValue(':users_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        return $this->hydrateAll($stmt);
    }

    private function bindMovie(PDOStatement $stmt, Movie $movie): void
    {
        $stmt->bindValue(':title', $movie->getTitle());
        $stmt->bindValue(':description', $movie->getDescription());
        $stmt->bindValue(':image', $movie->getImage());
        $stmt->bindValue(':trailer', $movie->getTrailer());
        $stmt->bindValue(':category', $movie->getCategory());
        $stmt->bindValue(':length', $movie->getLength());
        $stmt->bindValue(':users_id', $movie->getUserId(), PDO::PARAM_INT);
    }

    /**
     * @return Movie[]
     */
    private function hydrateAll(PDOStatement $stmt): array
    {
        $movies = [];

        foreach ($stmt->fetchAll() as $row) {
            $movies[] = $this->hydrate($row);
        }

        return $movies;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Movie
    {
        return (new Movie())
            ->setId((int) $row['id'])
            ->setTitle((string) $row['title'])
            ->setDescription($row['description'])
            ->setImage($row['image'])
            ->setTrailer($row['trailer'])
            ->setCategory($row['category'])
            ->setLength($row['length'])
            ->setUserId((int) $row['users_id'])
            ->setCreatedAt($row['created_at']);
    }
}
