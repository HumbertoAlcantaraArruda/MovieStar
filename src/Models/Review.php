<?php

declare(strict_types=1);

namespace MovieStar\Models;

use DateTimeImmutable;

class Review
{
    public const MIN_RATING = 1;
    public const MAX_RATING = 10;

    private ?int $id = null;
    private int $rating = 0;
    private ?string $review = null;
    private int $userId = 0;
    private int $movieId = 0;
    private ?string $createdAt = null;

    /** Nome do autor para exibição (preenchido só nas consultas com JOIN em users). */
    private ?string $authorName = null;

    public static function isValidRating(int $rating): bool
    {
        return $rating >= self::MIN_RATING && $rating <= self::MAX_RATING;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;
        return $this;
    }

    /** Nota inteira de MIN_RATING a MAX_RATING. */
    public function getRating(): int
    {
        return $this->rating;
    }

    public function setRating(int $rating): self
    {
        $this->rating = $rating;
        return $this;
    }

    /** Comentário (opcional). */
    public function getReview(): ?string
    {
        return $this->review;
    }

    public function setReview(?string $review): self
    {
        $this->review = $review;
        return $this;
    }

    /** Id do autor da avaliação (coluna users_id). */
    public function getUserId(): int
    {
        return $this->userId;
    }

    public function setUserId(int $userId): self
    {
        $this->userId = $userId;
        return $this;
    }

    /** Id do filme avaliado (coluna movies_id). */
    public function getMovieId(): int
    {
        return $this->movieId;
    }

    public function setMovieId(int $movieId): self
    {
        $this->movieId = $movieId;
        return $this;
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?string $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    /** Data de criação formatada (padrão pt-BR d/m/Y), ou '' se ausente/inválida. */
    public function getCreatedAtFormatted(string $format = 'd/m/Y'): string
    {
        if ($this->createdAt === null) {
            return '';
        }

        $date = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $this->createdAt);

        return $date === false ? '' : $date->format($format);
    }

    public function getAuthorName(): ?string
    {
        return $this->authorName;
    }

    public function setAuthorName(?string $authorName): self
    {
        $this->authorName = $authorName;
        return $this;
    }
}
