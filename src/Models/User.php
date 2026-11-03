<?php

declare(strict_types=1);

namespace MovieStar\Models;

class User
{
    public const DEFAULT_IMAGE = 'user.png';

    private ?int $id = null;
    private string $name = '';
    private string $lastname = '';
    private string $email = '';
    private string $password = '';
    private ?string $image = null;
    private ?string $token = null;
    private ?string $bio = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getLastname(): string
    {
        return $this->lastname;
    }

    public function setLastname(string $lastname): self
    {
        $this->lastname = $lastname;
        return $this;
    }

    public function getFullName(): string
    {
        return trim($this->name . ' ' . $this->lastname);
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    /** Hash da senha (nunca a senha em texto puro). */
    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $passwordHash): self
    {
        $this->password = $passwordHash;
        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function getImageOrDefault(): string
    {
        return $this->image ?? self::DEFAULT_IMAGE;
    }

    public function setImage(?string $image): self
    {
        $this->image = $image;
        return $this;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function setToken(?string $token): self
    {
        $this->token = $token;
        return $this;
    }

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function setBio(?string $bio): self
    {
        $this->bio = $bio;
        return $this;
    }
}
