<?php

declare(strict_types=1);

namespace MovieStar\Tests\Support;

use RuntimeException;

/**
 * Uma asserção falhou (o teste é contado como "falhou").
 */
class AssertionFailedException extends RuntimeException
{
}
