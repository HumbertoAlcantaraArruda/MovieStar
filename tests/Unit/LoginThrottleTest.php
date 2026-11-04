<?php

declare(strict_types=1);

namespace MovieStar\Tests\Unit;

use MovieStar\Core\LoginThrottle;
use MovieStar\Tests\Support\FakeLoginAttemptDAO;
use MovieStar\Tests\Support\TestCase;

class LoginThrottleTest extends TestCase
{
    private FakeLoginAttemptDAO $dao;
    private LoginThrottle $throttle;

    public function setUp(): void
    {
        $this->dao      = new FakeLoginAttemptDAO();
        $this->throttle = new LoginThrottle($this->dao, '10.0.0.1');
    }

    public function testAllowsUpToLimitPerEmailThenBlocks(): void
    {
        for ($i = 1; $i <= LoginThrottle::MAX_ATTEMPTS_PER_EMAIL; $i++) {
            $this->assertTrue($this->throttle->attempt('ana@example.com'), 'tentativa ' . $i);
        }

        $this->assertFalse($this->throttle->attempt('ana@example.com'), 'tentativa além do limite');
        $this->assertCount(LoginThrottle::MAX_ATTEMPTS_PER_EMAIL, $this->dao->attempts, 'tentativa bloqueada não é gravada');
    }

    public function testEmailIsNormalized(): void
    {
        for ($i = 0; $i < LoginThrottle::MAX_ATTEMPTS_PER_EMAIL; $i++) {
            $this->throttle->attempt($i % 2 === 0 ? '  ANA@Example.com ' : 'ana@example.com');
        }

        $this->assertFalse($this->throttle->attempt('Ana@EXAMPLE.com'), 'maiúsculas/espaços contam como o mesmo e-mail');
        $this->assertSame('ana@example.com', $this->dao->attempts[0]['email']);
    }

    public function testLongEmailAndIpAreTruncated(): void
    {
        $throttle = new LoginThrottle($this->dao, str_repeat('9', 60));
        $throttle->attempt(str_repeat('a', 300) . '@example.com');

        $this->assertSame(191, mb_strlen($this->dao->attempts[0]['email']));
        $this->assertSame(45, strlen($this->dao->attempts[0]['ip']));
    }

    public function testBlockExpiresAfterWindow(): void
    {
        for ($i = 0; $i < LoginThrottle::MAX_ATTEMPTS_PER_EMAIL; $i++) {
            $this->throttle->attempt('ana@example.com');
        }

        $this->dao->now += LoginThrottle::WINDOW_SECONDS - 1;
        $this->assertFalse($this->throttle->attempt('ana@example.com'), 'ainda dentro da janela');

        $this->dao->now += 1;
        $this->assertTrue($this->throttle->attempt('ana@example.com'), 'janela expirada');
        $this->assertCount(1, $this->dao->attempts, 'tentativas antigas foram apagadas');
    }

    public function testClearResetsEmailCounter(): void
    {
        for ($i = 0; $i < LoginThrottle::MAX_ATTEMPTS_PER_EMAIL; $i++) {
            $this->throttle->attempt('ana@example.com');
        }

        $this->throttle->clear(' ANA@example.com');

        $this->assertTrue($this->throttle->attempt('ana@example.com'), 'senha correta zera o contador do e-mail');
        $this->assertSame(1, $this->dao->countRecentByEmail('ana@example.com', LoginThrottle::WINDOW_SECONDS), 'só a nova tentativa fica');
    }

    public function testClearKeepsAttemptsAgainstOtherEmails(): void
    {
        // Tentativas contra outras contas, depois login correto na própria conta.
        for ($i = 0; $i < LoginThrottle::MAX_ATTEMPTS_PER_IP - 1; $i++) {
            $this->throttle->attempt('vitima' . $i . '@example.com');
        }

        $this->throttle->attempt('eu@example.com');
        $this->throttle->clear('eu@example.com');

        $this->assertSame(
            LoginThrottle::MAX_ATTEMPTS_PER_IP - 1,
            $this->dao->countRecentByIp('10.0.0.1', LoginThrottle::WINDOW_SECONDS),
            'logar na própria conta não limpa as tentativas feitas contra outras'
        );
        $this->assertTrue($this->throttle->attempt('outra@example.com'), 'vigésima tentativa do IP');
        $this->assertFalse($this->throttle->attempt('mais@example.com'), 'vigésima primeira é bloqueada');
    }

    public function testBlocksPerIpAcrossEmails(): void
    {
        for ($i = 1; $i <= LoginThrottle::MAX_ATTEMPTS_PER_IP; $i++) {
            $this->assertTrue($this->throttle->attempt('user' . $i . '@example.com'), 'tentativa ' . $i);
        }

        $this->assertFalse($this->throttle->attempt('novo@example.com'));

        $otherIp = new LoginThrottle($this->dao, '10.0.0.2');
        $this->assertTrue($otherIp->attempt('novo@example.com'), 'outro IP não é afetado');
    }

    public function testRecountAfterInsertBlocksParallelBurst(): void
    {
        for ($i = 0; $i < LoginThrottle::MAX_ATTEMPTS_PER_EMAIL - 1; $i++) {
            $this->dao->create('ana@example.com', '10.0.0.9');
        }

        // 4 gravadas: a quinta passa pela primeira checagem, mas outra requisição paralela
        // grava a sua entre a checagem e o INSERT; a recontagem depois do INSERT barra esta.
        $dao                     = $this->dao;
        $this->dao->beforeCreate = static function () use ($dao): void {
            $dao->create('ana@example.com', '10.0.0.9');
        };

        $this->assertFalse($this->throttle->attempt('ana@example.com'));
    }

    public function testBlockedMessage(): void
    {
        $this->assertSame('Muitas tentativas. Aguarde 15 minutos e tente novamente.', LoginThrottle::blockedMessage());
    }
}
