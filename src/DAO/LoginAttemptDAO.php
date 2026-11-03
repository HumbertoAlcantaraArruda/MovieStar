<?php

declare(strict_types=1);

namespace MovieStar\DAO;

use PDO;

/**
 * Tentativas de senha (login e confirmação da senha atual), usadas pelo limite contra força bruta.
 * Os horários são calculados pelo próprio MySQL (NOW()), sem depender do fuso do PHP.
 */
class LoginAttemptDAO
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function create(string $email, string $ip): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO login_attempts (email, ip_address) VALUES (:email, :ip)');
        $stmt->bindValue(':email', $email);
        $stmt->bindValue(':ip', $ip);
        $stmt->execute();
    }

    public function countRecentByEmail(string $email, int $seconds): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM login_attempts
              WHERE email = :email AND attempted_at > NOW() - INTERVAL :seconds SECOND'
        );
        $stmt->bindValue(':email', $email);
        $stmt->bindValue(':seconds', $seconds, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function countRecentByIp(string $ip, int $seconds): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM login_attempts
              WHERE ip_address = :ip AND attempted_at > NOW() - INTERVAL :seconds SECOND'
        );
        $stmt->bindValue(':ip', $ip);
        $stmt->bindValue(':seconds', $seconds, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function deleteByEmail(string $email): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM login_attempts WHERE email = :email');
        $stmt->bindValue(':email', $email);
        $stmt->execute();
    }

    /**
     * Remove tentativas que já saíram da janela de contagem (mantém a tabela pequena).
     */
    public function deleteOlderThan(int $seconds): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM login_attempts WHERE attempted_at <= NOW() - INTERVAL :seconds SECOND');
        $stmt->bindValue(':seconds', $seconds, PDO::PARAM_INT);
        $stmt->execute();
    }
}
