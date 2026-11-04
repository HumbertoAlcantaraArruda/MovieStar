<?php

declare(strict_types=1);

namespace MovieStar\Tests\Integration;

use MovieStar\DAO\DuplicateEmailException;
use MovieStar\DAO\UserDAO;
use MovieStar\Models\User;
use MovieStar\Tests\Support\DatabaseTestCase;
use PDOException;

class UserDAOTest extends DatabaseTestCase
{
    private UserDAO $dao;

    public function setUp(): void
    {
        parent::setUp();
        $this->dao = new UserDAO($this->pdo);
    }

    public function testCreateAndFind(): void
    {
        $user = $this->newUser('tmp_test_ana@example.com')->setBio("Linha 1\nLinha 2 <b>&</b>");
        $id   = $this->dao->create($user);

        $this->assertTrue($id > 0);

        $found = $this->dao->findById($id);
        $this->assertNotNull($found);
        $this->assertSame($id, $found->getId());
        $this->assertSame('Ana', $found->getName());
        $this->assertSame('Ação Souza', $found->getLastname(), 'UTF-8 preservado');
        $this->assertSame('tmp_test_ana@example.com', $found->getEmail());
        $this->assertSame($user->getToken(), $found->getToken());
        $this->assertSame("Linha 1\nLinha 2 <b>&</b>", $found->getBio(), 'gravado como veio (escape só na saída)');
        $this->assertNull($found->getImage());
        $this->assertTrue(password_verify('senha-secreta', $found->getPassword()), 'hash gravado');

        $byEmail = $this->dao->findByEmail('tmp_test_ana@example.com');
        $this->assertNotNull($byEmail);
        $this->assertSame($id, $byEmail->getId());
    }

    public function testFindMissingReturnsNull(): void
    {
        $this->assertNull($this->dao->findById(999));
        $this->assertNull($this->dao->findByEmail('ninguem@example.com'));
    }

    public function testFindByEmailIgnoresCase(): void
    {
        $id = $this->dao->create($this->newUser('tmp_test_Ana@Example.com'));

        $found = $this->dao->findByEmail('TMP_TEST_ANA@example.COM');
        $this->assertNotNull($found, 'collation utf8mb4_unicode_ci não diferencia maiúsculas');
        $this->assertSame($id, $found->getId());
    }

    public function testFindByEmailTreatsInputAsData(): void
    {
        $this->dao->create($this->newUser('tmp_test_ana@example.com'));

        $this->assertNull($this->dao->findByEmail("' OR '1'='1"));
        $this->assertNull($this->dao->findByEmail('%'));
    }

    public function testDuplicateEmailThrowsDomainException(): void
    {
        $this->dao->create($this->newUser('tmp_test_dup@example.com'));

        $this->assertThrows(DuplicateEmailException::class, function (): void {
            $this->dao->create($this->newUser('tmp_test_dup@example.com'));
        });

        // A UNIQUE vale também com outra caixa (mesma collation).
        $this->assertThrows(DuplicateEmailException::class, function (): void {
            $this->dao->create($this->newUser('TMP_TEST_DUP@EXAMPLE.COM'));
        });

        $this->assertSame(1, $this->countRows('users'));
    }

    public function testOtherDatabaseErrorsAreNotMaskedAsDuplicate(): void
    {
        // Nome maior que a coluna (VARCHAR(100)) em modo estrito: erro do banco, mas não de e-mail duplicado.
        $user    = $this->newUser('tmp_test_long@example.com')->setName(str_repeat('a', 150));
        $sqlMode = (string) $this->pdo->query('SELECT @@SESSION.sql_mode')->fetchColumn();
        $this->pdo->exec("SET SESSION sql_mode = 'STRICT_ALL_TABLES'");

        try {
            $error = $this->assertThrows(PDOException::class, function () use ($user): void {
                $this->dao->create($user);
            });
        } finally {
            $this->pdo->prepare('SET SESSION sql_mode = :mode')->execute([':mode' => $sqlMode]);
        }

        $this->assertFalse($error instanceof DuplicateEmailException);
    }

    public function testUpdateChangesProfileFieldsOnly(): void
    {
        $id   = $this->dao->create($this->newUser('tmp_test_ana@example.com'));
        $user = $this->dao->findById($id);
        $this->assertNotNull($user);

        $originalToken = $user->getToken();
        $originalHash  = $user->getPassword();

        $user->setName('Beatriz')->setLastname('Lima')->setBio('Nova bio')->setImage('abc.png')
            ->setEmail('outro@example.com')->setPassword('x')->setToken('y');
        $this->dao->update($user);

        $reloaded = $this->dao->findById($id);
        $this->assertNotNull($reloaded);
        $this->assertSame('Beatriz', $reloaded->getName());
        $this->assertSame('Lima', $reloaded->getLastname());
        $this->assertSame('Nova bio', $reloaded->getBio());
        $this->assertSame('abc.png', $reloaded->getImage());
        $this->assertSame('tmp_test_ana@example.com', $reloaded->getEmail(), 'e-mail não muda no update');
        $this->assertSame($originalHash, $reloaded->getPassword(), 'senha não muda no update');
        $this->assertSame($originalToken, $reloaded->getToken(), 'token não muda no update');
    }

    public function testUpdatePasswordChangesHashAndToken(): void
    {
        $id    = $this->dao->create($this->newUser('tmp_test_ana@example.com'));
        $other = $this->dao->create($this->newUser('tmp_test_bia@example.com'));

        $this->dao->updatePassword($id, password_hash('nova-senha-123', PASSWORD_DEFAULT), 'token-novo');

        $user = $this->dao->findById($id);
        $this->assertNotNull($user);
        $this->assertTrue(password_verify('nova-senha-123', $user->getPassword()));
        $this->assertSame('token-novo', $user->getToken());

        $untouched = $this->dao->findById($other);
        $this->assertNotNull($untouched);
        $this->assertTrue(password_verify('senha-secreta', $untouched->getPassword()), 'outros usuários não mudam');
    }

    private function newUser(string $email): User
    {
        return (new User())
            ->setName('Ana')
            ->setLastname('Ação Souza')
            ->setEmail($email)
            ->setPassword(password_hash('senha-secreta', PASSWORD_DEFAULT))
            ->setToken(bin2hex(random_bytes(25)));
    }
}
