<?php

declare(strict_types=1);

namespace MovieStar\DAO;

use MovieStar\Models\Review;
use PDO;
use PDOException;

class ReviewDAO
{
    /** Código de erro do MySQL para chave duplicada (SQLSTATE 23000). */
    private const MYSQL_DUPLICATE_KEY = 1062;

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Insere a avaliação e retorna o id gerado.
     *
     * @throws DuplicateReviewException se o usuário já avaliou o filme (garantida pela UNIQUE do banco)
     */
    public function create(Review $review): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO reviews (rating, review, users_id, movies_id)
             VALUES (:rating, :review, :users_id, :movies_id)'
        );
        $stmt->bindValue(':rating', $review->getRating(), PDO::PARAM_INT);
        $stmt->bindValue(':review', $review->getReview());
        $stmt->bindValue(':users_id', $review->getUserId(), PDO::PARAM_INT);
        $stmt->bindValue(':movies_id', $review->getMovieId(), PDO::PARAM_INT);

        try {
            $stmt->execute();
        } catch (PDOException $e) {
            if ($e->getCode() === '23000' && (int) ($e->errorInfo[1] ?? 0) === self::MYSQL_DUPLICATE_KEY) {
                throw new DuplicateReviewException('Avaliação duplicada.', 0, $e);
            }

            throw $e;
        }

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Avaliações do filme, das mais recentes para as mais antigas, com o nome do autor.
     *
     * @return Review[]
     */
    public function findByMovie(int $movieId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT r.*, u.name AS author_name, u.lastname AS author_lastname
               FROM reviews r
               JOIN users u ON u.id = r.users_id
              WHERE r.movies_id = :movies_id
              ORDER BY r.created_at DESC, r.id DESC'
        );
        $stmt->bindValue(':movies_id', $movieId, PDO::PARAM_INT);
        $stmt->execute();

        $reviews = [];

        foreach ($stmt->fetchAll() as $row) {
            $reviews[] = $this->hydrate($row)
                ->setAuthorName(trim($row['author_name'] . ' ' . $row['author_lastname']));
        }

        return $reviews;
    }

    public function hasUserReviewed(int $userId, int $movieId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM reviews WHERE users_id = :users_id AND movies_id = :movies_id LIMIT 1'
        );
        $stmt->bindValue(':users_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':movies_id', $movieId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchColumn() !== false;
    }

    /**
     * Média das notas do filme, ou null se ainda não houver avaliações.
     */
    public function getAverageRating(int $movieId): ?float
    {
        $stmt = $this->pdo->prepare('SELECT AVG(rating) FROM reviews WHERE movies_id = :movies_id');
        $stmt->bindValue(':movies_id', $movieId, PDO::PARAM_INT);
        $stmt->execute();

        $average = $stmt->fetchColumn();

        return $average === null || $average === false ? null : (float) $average;
    }

    /**
     * Médias de vários filmes em uma só consulta (para listagens).
     * Filmes sem avaliações ficam fora do resultado.
     *
     * @param int[] $movieIds
     * @return array<int, float> id do filme => média
     */
    public function getAverageRatings(array $movieIds): array
    {
        $movieIds = array_values(array_unique(array_map('intval', $movieIds)));

        if (count($movieIds) === 0) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($movieIds), '?'));

        $stmt = $this->pdo->prepare(
            "SELECT movies_id, AVG(rating) AS average
               FROM reviews
              WHERE movies_id IN ($placeholders)
              GROUP BY movies_id"
        );

        foreach ($movieIds as $index => $movieId) {
            $stmt->bindValue($index + 1, $movieId, PDO::PARAM_INT);
        }

        $stmt->execute();

        $averages = [];

        foreach ($stmt->fetchAll() as $row) {
            $averages[(int) $row['movies_id']] = (float) $row['average'];
        }

        return $averages;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Review
    {
        return (new Review())
            ->setId((int) $row['id'])
            ->setRating((int) $row['rating'])
            ->setReview($row['review'])
            ->setUserId((int) $row['users_id'])
            ->setMovieId((int) $row['movies_id'])
            ->setCreatedAt($row['created_at']);
    }
}
