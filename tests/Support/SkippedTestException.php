<?php

declare(strict_types=1);

namespace MovieStar\Tests\Support;

use RuntimeException;

/**
 * O teste não pôde rodar neste ambiente (ex.: php-cgi ausente) e é contado como "pulado".
 */
class SkippedTestException extends RuntimeException
{
}
