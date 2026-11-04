<?php

declare(strict_types=1);

namespace MovieStar\Tests\Unit;

use MovieStar\Core\Session;
use MovieStar\Tests\Support\TestCase;

/**
 * Métodos de Session que só leem/escrevem $_SESSION (sem session_start na CLI).
 */
class SessionTest extends TestCase
{
    private Session $session;

    public function setUp(): void
    {
        $_SESSION      = [];
        $this->session = new Session();
    }

    public function tearDown(): void
    {
        $_SESSION = [];
    }

    public function testCsrfTokenIsRandomHexAndStable(): void
    {
        $token = $this->session->csrfToken();

        $this->assertMatches('/^[a-f0-9]{64}\z/', $token);
        $this->assertSame($token, $this->session->csrfToken(), 'o token não muda dentro da sessão');

        $_SESSION = [];
        $this->assertNotSame($token, (new Session())->csrfToken(), 'outra sessão, outro token');
    }

    public function testValidCsrfToken(): void
    {
        $token = $this->session->csrfToken();

        $this->assertTrue($this->session->isValidCsrfToken($token));
    }

    public function testInvalidCsrfTokenValues(): void
    {
        $token = $this->session->csrfToken();

        $invalid = [
            'null (campo ausente)'      => null,
            'string vazia'              => '',
            'token errado'              => str_repeat('0', 64),
            'token com espaço'          => $token . ' ',
            'prefixo do token'          => substr($token, 0, 32),
            'array (csrf_token[]=...)'  => [$token],
            'array associativo'         => ['x' => $token],
            'inteiro'                   => 0,
            'booleano true'             => true,
        ];

        foreach ($invalid as $label => $value) {
            $this->assertFalse($this->session->isValidCsrfToken($value), $label);
        }
    }

    public function testCsrfTokenInvalidWithoutTokenInSession(): void
    {
        $this->assertFalse($this->session->isValidCsrfToken(''));
        $this->assertFalse($this->session->isValidCsrfToken('qualquer-coisa'));

        // Valor não-string na sessão nunca valida.
        $_SESSION['csrf_token'] = ['abc'];
        $this->assertFalse($this->session->isValidCsrfToken('abc'));
    }

    public function testFlashIsReadOnce(): void
    {
        $this->assertNull($this->session->pullFlash());

        $this->session->setFlash('Salvo!', 'success');
        $this->assertSame(['msg' => 'Salvo!', 'type' => 'success'], $this->session->pullFlash());
        $this->assertNull($this->session->pullFlash());
    }

    public function testOldInputKeepsOnlyStringsAndMatchingForm(): void
    {
        $this->session->setOldInput('auth_register', ['email' => 'a@b.com', 'list' => ['x'], 'n' => 5]);

        $this->assertSame([], $this->session->pullOldInput('auth_login'), 'outro formulário não consome');
        $this->assertSame(['email' => 'a@b.com'], $this->session->pullOldInput('auth_register'));
        $this->assertSame([], $this->session->pullOldInput('auth_register'), 'consumido uma única vez');
    }

    public function testAuthTokenValidation(): void
    {
        $this->assertFalse($this->session->isAuthTokenValid('abc'), 'sessão sem token');

        $_SESSION['auth_token'] = 'abc';
        $this->assertTrue($this->session->isAuthTokenValid('abc'));
        $this->assertFalse($this->session->isAuthTokenValid('abd'));
        $this->assertFalse($this->session->isAuthTokenValid(null));
    }

    public function testGetUserId(): void
    {
        $this->assertNull($this->session->getUserId());

        $_SESSION['user_id'] = '7';
        $this->assertSame(7, $this->session->getUserId());
    }
}
