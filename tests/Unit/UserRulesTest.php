<?php

declare(strict_types=1);

namespace MovieStar\Tests\Unit;

use MovieStar\Core\UserRules;
use MovieStar\Tests\Support\TestCase;

class UserRulesTest extends TestCase
{
    public function testNameWithinLimitIsValid(): void
    {
        $this->assertNull(UserRules::nameError('Ana', 'Souza'));
        $this->assertNull(UserRules::nameError(str_repeat('a', 100), str_repeat('b', 100)));
        // Limite em caracteres, não em bytes: 100 letras acentuadas passam.
        $this->assertNull(UserRules::nameError(str_repeat('é', 100), 'Souza'));
    }

    public function testNameTooLong(): void
    {
        $this->assertSame('Nome ou sobrenome muito longo.', UserRules::nameError(str_repeat('a', 101), 'Souza'));
        $this->assertSame('Nome ou sobrenome muito longo.', UserRules::nameError('Ana', str_repeat('é', 101)));
    }

    public function testValidPassword(): void
    {
        $this->assertNull(UserRules::passwordError('12345678', '12345678'));
        $this->assertNull(UserRules::passwordError(str_repeat('x', 72), str_repeat('x', 72)));
        // Espaços fazem parte da senha.
        $this->assertNull(UserRules::passwordError(' senha com espaço ', ' senha com espaço '));
    }

    public function testPasswordTooShort(): void
    {
        $this->assertSame('A senha deve ter ao menos 8 caracteres.', UserRules::passwordError('1234567', '1234567'));
        $this->assertSame('A senha deve ter ao menos 8 caracteres.', UserRules::passwordError('', ''));
    }

    public function testPasswordTooLongCountsBytes(): void
    {
        $this->assertSame('A senha deve ter no máximo 72 caracteres.', UserRules::passwordError(str_repeat('x', 73), str_repeat('x', 73)));
        // 37 letras acentuadas = 74 bytes (o bcrypt ignoraria o excedente).
        $this->assertSame('A senha deve ter no máximo 72 caracteres.', UserRules::passwordError(str_repeat('é', 37), str_repeat('é', 37)));
    }

    public function testPasswordWithNullByte(): void
    {
        $password = "abcd\0efgh";
        $this->assertSame('A senha contém caracteres inválidos.', UserRules::passwordError($password, $password));
    }

    public function testPasswordConfirmationMismatch(): void
    {
        $this->assertSame('As senhas não conferem.', UserRules::passwordError('12345678', '12345679'));
        $this->assertSame('As senhas não conferem.', UserRules::passwordError('12345678', '12345678 '));
    }
}
