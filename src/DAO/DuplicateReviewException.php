<?php

declare(strict_types=1);

namespace MovieStar\DAO;

use RuntimeException;

/**
 * O usuário já avaliou o filme (violação da UNIQUE uq_reviews_user_movie).
 * Mantém o código de erro específico do MySQL dentro da camada de DAO.
 */
class DuplicateReviewException extends RuntimeException
{
}
