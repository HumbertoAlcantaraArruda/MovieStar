<?php

declare(strict_types=1);

namespace MovieStar\Core;

/**
 * Encapsula $_SESSION: autenticação, mensagens flash e token CSRF.
 */
class Session
{
    private const KEY_USER_ID = 'user_id';
    private const KEY_FLASH   = 'flash';
    private const KEY_CSRF    = 'csrf_token';

    public function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function login(int $userId): void
    {
        session_regenerate_id(true);
        $_SESSION[self::KEY_USER_ID] = $userId;
    }

    public function logout(): void
    {
        $_SESSION = [];
        session_regenerate_id(true);
    }

    public function getUserId(): ?int
    {
        return isset($_SESSION[self::KEY_USER_ID]) ? (int) $_SESSION[self::KEY_USER_ID] : null;
    }

    public function setFlash(string $message, string $type = 'success'): void
    {
        $_SESSION[self::KEY_FLASH] = ['msg' => $message, 'type' => $type];
    }

    /**
     * Retorna e remove a mensagem flash.
     *
     * @return array{msg: string, type: string}|null
     */
    public function pullFlash(): ?array
    {
        $flash = $_SESSION[self::KEY_FLASH] ?? null;
        unset($_SESSION[self::KEY_FLASH]);

        return $flash;
    }

    public function csrfToken(): string
    {
        if (empty($_SESSION[self::KEY_CSRF])) {
            $_SESSION[self::KEY_CSRF] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::KEY_CSRF];
    }

    public function isValidCsrfToken(?string $token): bool
    {
        return $token !== null
            && isset($_SESSION[self::KEY_CSRF])
            && hash_equals($_SESSION[self::KEY_CSRF], $token);
    }
}
