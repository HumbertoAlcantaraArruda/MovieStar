<?php

declare(strict_types=1);

namespace MovieStar\Tests\Integration;

use MovieStar\DAO\UserDAO;
use MovieStar\Tests\Support\ControllerTestCase;
use MovieStar\Tests\Support\ImageFactory;
use MovieStar\Tests\Support\TempDirectory;

class ProfileControllerTest extends ControllerTestCase
{
    private int $userId;

    public function setUp(): void
    {
        parent::setUp();
        $this->userId = $this->createUser('tmp_test_perfil@example.com');
    }

    public function testRequiresLoginAndValidType(): void
    {
        $guest = $this->post('profile_edit', ['type' => 'profile', 'name' => 'X', 'lastname' => 'Y']);
        $this->assertRedirect($guest, 'auth.php', 'Faça login para continuar.');

        $unknown = $this->post('profile_edit', ['type' => 'outro'], ['userId' => $this->userId]);
        $this->assertRedirect($unknown, 'editprofile.php', 'Requisição inválida. Tente novamente.');
    }

    public function testProfileValidations(): void
    {
        $cases = [
            'Preencha nome e sobrenome.'               => ['lastname' => ''],
            'Nome ou sobrenome muito longo.'           => ['name' => str_repeat('a', 101)],
            'A bio deve ter no máximo 1000 caracteres.' => ['bio' => str_repeat('b', 1001)],
        ];

        foreach ($cases as $message => $override) {
            $result = $this->post('profile_edit', $override + $this->profileData(), ['userId' => $this->userId]);

            $this->assertRedirect($result, 'editprofile.php', $message);
        }

        $user = (new UserDAO($this->pdo))->findById($this->userId);
        $this->assertNotNull($user);
        $this->assertSame('Ana', $user->getName(), 'nada mudou');
    }

    public function testProfileUpdateWithPhoto(): void
    {
        $result = $this->post('profile_edit', $this->profileData(), [
            'userId' => $this->userId,
            'files'  => ['image' => ['eu.jpg', ImageFactory::jpeg(), 'image/jpeg']],
        ]);

        $this->assertRedirect($result, 'profile.php?id=' . $this->userId, 'Perfil atualizado com sucesso!', 'success');

        $user = (new UserDAO($this->pdo))->findById($this->userId);
        $this->assertNotNull($user);
        $this->assertSame('Beatriz', $user->getName());
        $this->assertSame('Lima', $user->getLastname());
        $this->assertSame('Minha bio', $user->getBio());
        $this->assertMatches('/^[a-f0-9]{32}\.jpg\z/', (string) $user->getImage());
        $this->assertSame([$user->getImage()], TempDirectory::listFiles($this->uploadDir));
    }

    public function testPasswordChangeValidations(): void
    {
        $cases = [
            'Preencha a senha atual e a nova senha.' => ['current_password' => ''],
            'A senha atual está incorreta.'          => ['current_password' => 'errada'],
            'As senhas não conferem.'                => ['confirmpassword' => 'diferente-123'],
            'A senha deve ter ao menos 8 caracteres.' => ['password' => 'curta', 'confirmpassword' => 'curta'],
        ];

        foreach ($cases as $message => $override) {
            $result = $this->post('profile_edit', $override + $this->passwordData(), ['userId' => $this->userId]);

            $this->assertRedirect($result, 'editprofile.php', $message);
            $this->assertFalse(isset($result['session']['old_input']), 'senhas nunca voltam para o formulário');
        }

        $user = (new UserDAO($this->pdo))->findById($this->userId);
        $this->assertNotNull($user);
        $this->assertTrue(password_verify('senha-secreta', $user->getPassword()), 'senha não mudou');
    }

    public function testPasswordChangeRenewsToken(): void
    {
        $before = (new UserDAO($this->pdo))->findById($this->userId);
        $this->assertNotNull($before);

        $result = $this->post('profile_edit', $this->passwordData(), ['userId' => $this->userId]);
        $this->assertRedirect($result, 'editprofile.php', 'Senha alterada com sucesso!', 'success');

        $after = (new UserDAO($this->pdo))->findById($this->userId);
        $this->assertNotNull($after);
        $this->assertTrue(password_verify('nova-senha-456', $after->getPassword()));
        $this->assertNotSame($before->getToken(), $after->getToken(), 'token novo encerra as outras sessões');
        $this->assertSame($after->getToken(), $result['session']['auth_token'] ?? null, 'esta sessão segue logada');
    }

    /**
     * @return array<string, string>
     */
    private function profileData(): array
    {
        return ['type' => 'profile', 'name' => 'Beatriz', 'lastname' => 'Lima', 'bio' => 'Minha bio'];
    }

    /**
     * @return array<string, string>
     */
    private function passwordData(): array
    {
        return [
            'type'             => 'password',
            'current_password' => 'senha-secreta',
            'password'         => 'nova-senha-456',
            'confirmpassword'  => 'nova-senha-456',
        ];
    }
}
