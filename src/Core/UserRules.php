<?php

declare(strict_types=1);

namespace MovieStar\Core;

/**
 * Regras de validação de usuário compartilhadas (cadastro e perfil).
 */
class UserRules
{
    public const MIN_PASSWORD_LENGTH = 8;
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
     * Mensagem de erro para uma nova senha (tamanho mínimo e confirmação), ou null se válida.
     */
    public static function passwordError(string $password, string $confirm): ?string
    {
        if (strlen($password) < self::MIN_PASSWORD_LENGTH) {
            return 'A senha deve ter ao menos ' . self::MIN_PASSWORD_LENGTH . ' caracteres.';
        }

        if ($password !== $confirm) {
            return 'As senhas não conferem.';
        }

        return null;
    }
}
