<?php

declare(strict_types=1);

namespace MovieStar\Tests\Support;

use Throwable;

/**
 * Base dos testes: cada método público "test*" é um teste, rodado em uma instância nova
 * (setUp() antes, tearDown() depois, mesmo se o teste falhar).
 */
abstract class TestCase
{
    private int $assertions = 0;

    public function setUp(): void
    {
    }

    public function tearDown(): void
    {
    }

    public function getAssertionCount(): int
    {
        return $this->assertions;
    }

    /**
     * @param mixed $expected
     * @param mixed $actual
     */
    protected function assertSame($expected, $actual, string $message = ''): void
    {
        $this->check(
            $expected === $actual,
            $message,
            'esperado ' . $this->export($expected) . ', obtido ' . $this->export($actual)
        );
    }

    /**
     * @param mixed $notExpected
     * @param mixed $actual
     */
    protected function assertNotSame($notExpected, $actual, string $message = ''): void
    {
        $this->check($notExpected !== $actual, $message, 'valor não deveria ser ' . $this->export($actual));
    }

    protected function assertTrue(bool $condition, string $message = ''): void
    {
        $this->check($condition, $message, 'esperado true, obtido false');
    }

    protected function assertFalse(bool $condition, string $message = ''): void
    {
        $this->check(!$condition, $message, 'esperado false, obtido true');
    }

    /**
     * @param mixed $actual
     */
    protected function assertNull($actual, string $message = ''): void
    {
        $this->check($actual === null, $message, 'esperado null, obtido ' . $this->export($actual));
    }

    /**
     * @param mixed $actual
     */
    protected function assertNotNull($actual, string $message = ''): void
    {
        $this->check($actual !== null, $message, 'valor não deveria ser null');
    }

    /**
     * @param mixed $actual
     */
    protected function assertInstanceOf(string $class, $actual, string $message = ''): void
    {
        $this->check(
            $actual instanceof $class,
            $message,
            'esperado ' . $class . ', obtido ' . (is_object($actual) ? get_class($actual) : gettype($actual))
        );
    }

    /**
     * @param array<mixed> $array
     */
    protected function assertCount(int $expected, array $array, string $message = ''): void
    {
        $this->check(count($array) === $expected, $message, 'esperados ' . $expected . ' itens, obtidos ' . count($array));
    }

    protected function assertStringContains(string $needle, string $haystack, string $message = ''): void
    {
        $this->check(
            strpos($haystack, $needle) !== false,
            $message,
            $this->export($haystack) . ' não contém ' . $this->export($needle)
        );
    }

    protected function assertStringNotContains(string $needle, string $haystack, string $message = ''): void
    {
        $this->check(
            strpos($haystack, $needle) === false,
            $message,
            $this->export($haystack) . ' não deveria conter ' . $this->export($needle)
        );
    }

    protected function assertMatches(string $pattern, string $actual, string $message = ''): void
    {
        $this->check(preg_match($pattern, $actual) === 1, $message, $this->export($actual) . ' não casa com ' . $pattern);
    }

    /**
     * Executa $callback e exige que ele lance $class (e, se informado, uma mensagem contendo $messagePart).
     */
    protected function assertThrows(string $class, callable $callback, ?string $messagePart = null, string $message = ''): Throwable
    {
        try {
            $callback();
        } catch (AssertionFailedException $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->check(
                $e instanceof $class,
                $message,
                'esperada ' . $class . ', lançada ' . get_class($e) . ': ' . $e->getMessage()
            );

            if ($messagePart !== null) {
                $this->assertStringContains($messagePart, $e->getMessage(), $message);
            }

            return $e;
        }

        $this->check(false, $message, 'esperada ' . $class . ', nada foi lançado');

        // Inalcançável (check() lança), mas o tipo de retorno exige um valor.
        throw new AssertionFailedException('');
    }

    /**
     * @return never
     */
    protected function fail(string $message): void
    {
        $this->check(false, $message, 'falha explícita');
    }

    /**
     * @return never
     */
    protected function skip(string $reason): void
    {
        throw new SkippedTestException($reason);
    }

    private function check(bool $condition, string $message, string $detail): void
    {
        $this->assertions++;

        if (!$condition) {
            throw new AssertionFailedException(($message !== '' ? $message . ' — ' : '') . $detail);
        }
    }

    /**
     * @param mixed $value
     */
    private function export($value): string
    {
        if (is_string($value)) {
            $text = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return $text !== false ? $text : var_export($value, true);
        }

        if (is_object($value)) {
            return get_class($value);
        }

        $text = var_export($value, true);

        return strlen($text) > 300 ? substr($text, 0, 300) . '...' : $text;
    }
}
