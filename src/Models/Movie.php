<?php

declare(strict_types=1);

namespace MovieStar\Models;

class Movie
{
    /** Categorias aceitas no cadastro (lista fixa). */
    public const CATEGORIES = [
        'Ação',
        'Animação',
        'Aventura',
        'Comédia',
        'Documentário',
        'Drama',
        'Fantasia',
        'Ficção Científica',
        'Romance',
        'Suspense',
        'Terror',
    ];

    /** Imagem exibida quando o filme não tem capa (em img/movies/). */
    public const DEFAULT_IMAGE = 'movie_cover.jpg';

    private ?int $id = null;
    private string $title = '';
    private ?string $description = null;
    private ?string $image = null;
    private ?string $trailer = null;
    private ?string $category = null;
    private ?string $length = null;
    private int $userId = 0;
    private ?string $createdAt = null;

    public static function isValidCategory(string $category): bool
    {
        return in_array($category, self::CATEGORIES, true);
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

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    /** Nome do arquivo em img/movies/ (ou null se não houver capa). */
    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): self
    {
        $this->image = $image;
        return $this;
    }

    /** Nome do arquivo da capa, ou a imagem padrão quando não houver. */
    public function getImageOrDefault(): string
    {
        return $this->image ?? self::DEFAULT_IMAGE;
    }

    /** URL de incorporação do YouTube (https://www.youtube.com/embed/ID) ou null. */
    public function getTrailer(): ?string
    {
        return $this->trailer;
    }

    public function setTrailer(?string $trailer): self
    {
        $this->trailer = $trailer;
        return $this;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(?string $category): self
    {
        $this->category = $category;
        return $this;
    }

    public function getLength(): ?string
    {
        return $this->length;
    }

    public function setLength(?string $length): self
    {
        $this->length = $length;
        return $this;
    }

    /** Id do usuário que cadastrou o filme (coluna users_id). */
    public function getUserId(): int
    {
        return $this->userId;
    }

    public function setUserId(int $userId): self
    {
        $this->userId = $userId;
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
}
