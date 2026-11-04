<?php

declare(strict_types=1);

namespace MovieStar\Tests\Integration;

use MovieStar\Core\LoginThrottle;
use MovieStar\DAO\LoginAttemptDAO;
use MovieStar\Tests\Support\DatabaseTestCase;
use PDO;

class LoginAttemptDAOTest extends DatabaseTestCase
{
    private const WINDOW = LoginThrottle::WINDOW_SECONDS;

    private LoginAttemptDAO $dao;

    public function setUp(): void
    {
        parent::setUp();
        $this->dao = new LoginAttemptDAO($this->pdo);
    }

    public function testCountsByEmailAndIp(): void
    {
        $this->dao->create('ana@example.com', '10.0.0.1');
        $this->dao->create('ana@example.com', '10.0.0.2');
        $this->dao->create('bia@example.com', '10.0.0.1');

        $this->assertSame(2, $this->dao->countRecentByEmail('ana@example.com', self::WINDOW));
        $this->assertSame(1, $this->dao->countRecentByEmail('bia@example.com', self::WINDOW));
        $this->assertSame(0, $this->dao->countRecentByEmail('ninguem@example.com', self::WINDOW));
        $this->assertSame(2, $this->dao->countRecentByIp('10.0.0.1', self::WINDOW));
        $this->assertSame(1, $this->dao->countRecentByIp('10.0.0.2', self::WINDOW));
    }

    public function testOldAttemptsAreNotCounted(): void
    {
        $this->dao->create('ana@example.com', '10.0.0.1');
        $this->insertAgo('ana@example.com', '10.0.0.1', self::WINDOW + 60);

        $this->assertSame(1, $this->dao->countRecentByEmail('ana@example.com', self::WINDOW));
        $this->assertSame(1, $this->dao->countRecentByIp('10.0.0.1', self::WINDOW));
    }

    public function testDeleteOlderThanKeepsRecent(): void
    {
        $this->dao->create('ana@example.com', '10.0.0.1');
        $this->insertAgo('velho@example.com', '10.0.0.9', self::WINDOW + 1);
        $this->insertAgo('velho@example.com', '10.0.0.9', self::WINDOW * 10);

        $this->dao->deleteOlderThan(self::WINDOW);

        $this->assertSame(1, $this->countRows('login_attempts'));
        $this->assertSame(1, $this->dao->countRecentByEmail('ana@example.com', self::WINDOW));
    }

    public function testDeleteByEmailOnlyThatEmail(): void
    {
        $this->dao->create('ana@example.com', '10.0.0.1');
        $this->dao->create('ana@example.com', '10.0.0.1');
        $this->dao->create('bia@example.com', '10.0.0.1');

        $this->dao->deleteByEmail('ana@example.com');

        $this->assertSame(0, $this->dao->countRecentByEmail('ana@example.com', self::WINDOW));
        $this->assertSame(1, $this->dao->countRecentByEmail('bia@example.com', self::WINDOW));
    }

    public function testThrottleWithRealDao(): void
    {
        $throttle = new LoginThrottle($this->dao, '192.0.2.10');

        for ($i = 0; $i < LoginThrottle::MAX_ATTEMPTS_PER_EMAIL; $i++) {
            $this->assertTrue($throttle->attempt('Ana@Example.com'));
        }

        $this->assertFalse($throttle->attempt('ana@example.com'));
        $this->assertSame(LoginThrottle::MAX_ATTEMPTS_PER_EMAIL, $this->countRows('login_attempts'));

        $throttle->clear('ana@example.com');
        $this->assertTrue($throttle->attempt('ana@example.com'));
    }

    private function insertAgo(string $email, string $ip, int $secondsAgo): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO login_attempts (email, ip_address, attempted_at) VALUES (:email, :ip, NOW() - INTERVAL :ago SECOND)'
        );
        $stmt->bindValue(':email', $email);
        $stmt->bindValue(':ip', $ip);
        $stmt->bindValue(':ago', $secondsAgo, PDO::PARAM_INT);
        $stmt->execute();
    }
}
