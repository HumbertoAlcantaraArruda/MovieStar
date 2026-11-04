<?php

declare(strict_types=1);

namespace MovieStar\DAO;

use RuntimeException;

/**
 * Já existe um usuário com o e-mail (violação da UNIQUE uq_users_email).
 * Mantém o código de erro específico do MySQL dentro da camada de DAO.
 */
class DuplicateEmailException extends RuntimeException
{
}
