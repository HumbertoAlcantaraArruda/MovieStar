<?php

declare(strict_types=1);

namespace MovieStar\Tests\Support;

use Closure;
use MovieStar\DAO\LoginAttemptDAO;

/**
 * LoginAttemptDAO em memória, com relógio controlado pelo teste (para testar o LoginThrottle sem banco).
 */
class FakeLoginAttemptDAO extends LoginAttemptDAO
{
    /** @var array<int, array{email: string, ip: string, at: int}> */
    public array $attempts = [];

    public int $now = 1000000;

    /** Executado antes de cada INSERT (simula requisições paralelas gravando no meio do caminho). */
    public ?Closure $beforeCreate = null;

    public function __construct()
    {
        // Sem PDO: nenhum método da classe-mãe é usado.
    }

    public function create(string $email, string $ip): void
    {
        if ($this->beforeCreate !== null) {
            $callback           = $this->beforeCreate;
            $this->beforeCreate = null;
            $callback();
        }

        $this->attempts[] = ['email' => $email, 'ip' => $ip, 'at' => $this->now];
    }

    public function countRecentByEmail(string $email, int $seconds): int
    {
        return $this->countRecent('email', $email, $seconds);
    }

    public function countRecentByIp(string $ip, int $seconds): int
    {
        return $this->countRecent('ip', $ip, $seconds);
    }

    public function deleteByEmail(string $email): void
    {
        $this->attempts = array_values(array_filter($this->attempts, static function (array $a) use ($email): bool {
            return $a['email'] !== $email;
        }));
    }

    public function deleteOlderThan(int $seconds): void
    {
        $limit          = $this->now - $seconds;
        $this->attempts = array_values(array_filter($this->attempts, static function (array $a) use ($limit): bool {
            return $a['at'] > $limit;
        }));
    }

    private function countRecent(string $field, string $value, int $seconds): int
    {
        $count = 0;

        foreach ($this->attempts as $attempt) {
            if ($attempt[$field] === $value && $attempt['at'] > $this->now - $seconds) {
                $count++;
            }
        }

        return $count;
    }
}
