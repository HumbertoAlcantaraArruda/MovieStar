<?php

declare(strict_types=1);

namespace MovieStar\Core;

use MovieStar\Models\User;

/**
 * Controle de acesso centralizado: exigir login, bloquear páginas de visitante
 * e checar se o usuário logado é dono de um recurso.
 */
class Auth
{
    private const LOGIN_PATH = 'auth.php';

    private Session $session;
    private ?User $currentUser;
    private string $baseUrl;

    public function __construct(Session $session, ?User $currentUser, string $baseUrl)
    {
        $this->session     = $session;
        $this->currentUser = $currentUser;
        $this->baseUrl     = $baseUrl;
    }

    public function isLoggedIn(): bool
    {
        return $this->currentUser !== null;
    }

    public function getUser(): ?User
    {
        return $this->currentUser;
    }

    /**
     * Garante que há usuário logado; caso contrário, redireciona para o login com flash.
     */
    public function requireLogin(): User
    {
        if ($this->currentUser === null) {
            $this->redirect(self::LOGIN_PATH, 'Faça login para continuar.', 'error');
        }

        return $this->currentUser;
    }

    /**
     * Para páginas só de visitante (ex.: auth.php): usuário logado vai para $path.
     */
    public function requireGuest(string $path = ''): void
    {
        if ($this->currentUser !== null) {
            $this->redirect($path);
        }
    }

    /**
     * Indica se o usuário logado é o dono do recurso cujo dono é $ownerId.
     */
    public function owns(?int $ownerId): bool
    {
        return $this->currentUser !== null
            && $ownerId !== null
            && $ownerId === (int) $this->currentUser->getId();
    }

    /**
     * @return never
     */
    private function redirect(string $path, ?string $message = null, string $type = 'success'): void
    {
        if ($message !== null) {
            $this->session->setFlash($message, $type);
        }

        header('Location: ' . $this->baseUrl . $path);
        exit;
    }
}
