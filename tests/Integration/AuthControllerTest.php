<?php

declare(strict_types=1);

namespace MovieStar\Tests\Integration;

use MovieStar\Core\LoginThrottle;
use MovieStar\DAO\UserDAO;
use MovieStar\Tests\Support\ControllerTestCase;

class AuthControllerTest extends ControllerTestCase
{
    private const EMAIL = 'tmp_test_auth@example.com';

    public function testRejectsMissingOrInvalidCsrf(): void
    {
        foreach (['ausente' => null, 'errado' => 'x', 'array' => [self::CSRF]] as $label => $token) {
            $result = $this->post('auth', ['type' => 'register', 'csrf_token' => $token] + $this->registerData());

            $this->assertRedirect($result, 'auth.php', 'Requisição inválida. Tente novamente.');
            $this->assertSame(0, $this->countRows('users'), 'CSRF ' . $label);
        }
    }

    public function testRegisterValidations(): void
    {
        $cases = [
            'Preencha todos os campos.'                => ['name' => '   '],
            'Nome ou sobrenome muito longo.'           => ['lastname' => str_repeat('a', 101)],
            'Informe um e-mail válido.'                => ['email' => 'nao-e-email'],
            'A senha deve ter ao menos 8 caracteres.'  => ['password' => 'curta', 'confirmpassword' => 'curta'],
            'As senhas não conferem.'                  => ['confirmpassword' => 'outra-senha'],
            'A senha deve ter no máximo 72 caracteres.' => ['password' => str_repeat('x', 73), 'confirmpassword' => str_repeat('x', 73)],
        ];

        foreach ($cases as $message => $override) {
            $result = $this->post('auth', $override + $this->registerData());

            $this->assertRedirect($result, 'auth.php', $message);
        }

        // E-mail longo demais (o limite da coluna é 191).
        $result = $this->post('auth', ['email' => str_repeat('a', 180) . '@example.com'] + $this->registerData());
        $this->assertRedirect($result, 'auth.php', 'Informe um e-mail válido.');

        $this->assertSame(0, $this->countRows('users'));
    }

    public function testArrayFieldsAreTreatedAsEmpty(): void
    {
        $result = $this->post('auth', ['email' => ['a@b.com']] + $this->registerData());

        $this->assertRedirect($result, 'auth.php', 'Preencha todos os campos.');
    }

    public function testRegisterErrorKeepsInputButNeverPassword(): void
    {
        $result = $this->post('auth', ['confirmpassword' => 'outra-senha'] + $this->registerData());

        $this->assertSame([
            'form' => 'auth_register',
            'data' => ['email' => self::EMAIL, 'name' => 'Ana', 'lastname' => 'Souza'],
        ], $result['session']['old_input'] ?? null);
    }

    public function testRegisterSuccessLogsIn(): void
    {
        $result = $this->post('auth', $this->registerData());

        $this->assertRedirect($result, '', 'Cadastro realizado com sucesso!', 'success');

        $user = (new UserDAO($this->pdo))->findByEmail(self::EMAIL);
        $this->assertNotNull($user);
        $this->assertSame('Ana', $user->getName());
        $this->assertTrue(password_verify('senha-forte-123', $user->getPassword()), 'senha gravada com hash');
        $this->assertSame($user->getId(), $result['session']['user_id'] ?? null, 'usuário logado na sessão');
        $this->assertSame($user->getToken(), $result['session']['auth_token'] ?? null);
        $this->assertFalse(isset($result['session']['csrf_token']), 'token CSRF renovado no login');
    }

    public function testRegisterExistingEmail(): void
    {
        $this->createUser(self::EMAIL);

        $result = $this->post('auth', ['email' => strtoupper(self::EMAIL)] + $this->registerData());

        $this->assertRedirect($result, 'auth.php', 'Este e-mail já está cadastrado.');
        $this->assertSame(1, $this->countRows('users'));
    }

    public function testConcurrentRegisterWithSameEmailIsFriendlyError(): void
    {
        // Corrida: a checagem prévia não vê o e-mail, mas o INSERT esbarra na UNIQUE.
        // Antes da correção, a PDOException escapava e virava erro 500.
        $this->createUser(self::EMAIL);

        $result = $this->post('auth', $this->registerData(), ['spec' => ['raceOnEmail' => true]]);

        $this->assertRedirect($result, 'auth.php', 'Este e-mail já está cadastrado.');
        $this->assertFalse(isset($result['session']['user_id']), 'ninguém foi logado');
        $this->assertSame(1, $this->countRows('users'));
    }

    public function testLoginWrongAndRightPassword(): void
    {
        $id = $this->createUser(self::EMAIL);

        $wrong = $this->post('auth', ['type' => 'login', 'email' => self::EMAIL, 'password' => 'errada']);
        $this->assertRedirect($wrong, 'auth.php', 'E-mail ou senha incorretos.');
        $this->assertSame(['form' => 'auth_login', 'data' => ['email' => self::EMAIL]], $wrong['session']['old_input'] ?? null);

        $unknown = $this->post('auth', ['type' => 'login', 'email' => 'ninguem@example.com', 'password' => 'senha-secreta']);
        $this->assertRedirect($unknown, 'auth.php', 'E-mail ou senha incorretos.', 'error');

        $right = $this->post('auth', ['type' => 'login', 'email' => self::EMAIL, 'password' => 'senha-secreta']);
        $this->assertRedirect($right, '', 'Bem-vindo(a), Ana!', 'success');
        $this->assertSame($id, $right['session']['user_id'] ?? null);
        $this->assertSame(0, $this->countAttempts(self::EMAIL), 'login correto zera as tentativas do e-mail');
    }

    public function testLoginIsThrottled(): void
    {
        $this->createUser(self::EMAIL);

        for ($i = 0; $i < LoginThrottle::MAX_ATTEMPTS_PER_EMAIL; $i++) {
            $this->pdo->exec("INSERT INTO login_attempts (email, ip_address) VALUES ('" . self::EMAIL . "', '10.9.9.9')");
        }

        // Nem a senha correta passa durante o bloqueio.
        $result = $this->post('auth', ['type' => 'login', 'email' => self::EMAIL, 'password' => 'senha-secreta']);

        $this->assertRedirect($result, 'auth.php', LoginThrottle::blockedMessage());
        $this->assertFalse(isset($result['session']['user_id']));
    }

    public function testLoggedUserCanOnlyLogout(): void
    {
        $id = $this->createUser(self::EMAIL);

        $register = $this->post('auth', $this->registerData(), ['userId' => $id]);
        $this->assertSame('/MovieStar/', $register['location'], 'logado não acessa cadastro/login');
        $this->assertNull($register['flash']);

        $logout = $this->post('auth', ['type' => 'logout'], ['userId' => $id]);
        $this->assertRedirect($logout, '', 'Você saiu da sua conta.', 'success');
        $this->assertFalse(isset($logout['session']['user_id']), 'sessão limpa');

        $badCsrf = $this->post('auth', ['type' => 'logout', 'csrf_token' => 'x'], ['userId' => $id]);
        $this->assertRedirect($badCsrf, '', 'Requisição inválida. Tente novamente.');
        $this->assertSame($id, $badCsrf['session']['user_id'] ?? null, 'logout sem CSRF não desloga');
    }

    public function testGetDoesNothing(): void
    {
        $result = $this->post('auth', [], ['method' => 'GET']);

        $this->assertTrue($result['returned']);
        $this->assertNull($result['location']);
    }

    /**
     * @return array<string, string>
     */
    private function registerData(): array
    {
        return [
            'type'            => 'register',
            'name'            => 'Ana',
            'lastname'        => 'Souza',
            'email'           => self::EMAIL,
            'password'        => 'senha-forte-123',
            'confirmpassword' => 'senha-forte-123',
        ];
    }

    private function countAttempts(string $email): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE email = :email');
        $stmt->execute([':email' => $email]);

        return (int) $stmt->fetchColumn();
    }
}
