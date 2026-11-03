<?php

declare(strict_types=1);

namespace MovieStar\Core;

/**
 * Regras de validação de usuário compartilhadas (cadastro e perfil).
 */
class UserRules
{
    public const MIN_PASSWORD_LENGTH = 8;
    /** Limite do bcrypt (em bytes): o que passasse disso seria ignorado sem aviso. */
    public const MAX_PASSWORD_LENGTH = 72;
    public const MAX_FIELD_LENGTH    = 100;
    public const MAX_BIO_LENGTH      = 1000;

    /**
     * Mensagem de erro para nome/sobrenome longos demais, ou null se válidos.
     */
    public static function nameError(string $name, string $lastname): ?string
    {
        if (mb_strlen($name) > self::MAX_FIELD_LENGTH || mb_strlen($lastname) > self::MAX_FIELD_LENGTH) {
            return 'Nome ou sobrenome muito longo.';
        }

        return null;
    }

    /**
     * Mensagem de erro para uma nova senha (tamanho, caracteres e confirmação), ou null se válida.
     */
    public static function passwordError(string $password, string $confirm): ?string
    {
        if (strlen($password) < self::MIN_PASSWORD_LENGTH) {
            return 'A senha deve ter ao menos ' . self::MIN_PASSWORD_LENGTH . ' caracteres.';
        }

        // Conta bytes, como o bcrypt (letras acentuadas valem 2).
        if (strlen($password) > self::MAX_PASSWORD_LENGTH) {
            return 'A senha deve ter no máximo ' . self::MAX_PASSWORD_LENGTH . ' caracteres.';
        }

        // Byte nulo: o bcrypt cortaria a senha nele (PHP 7.4) ou password_hash() lançaria ValueError (PHP 8).
        if (strpos($password, "\0") !== false) {
            return 'A senha contém caracteres inválidos.';
        }

        if ($password !== $confirm) {
            return 'As senhas não conferem.';
        }

        return null;
    }
}
