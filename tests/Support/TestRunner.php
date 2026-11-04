<?php

declare(strict_types=1);

namespace MovieStar\Tests\Support;

use ReflectionClass;
use ReflectionMethod;
use Throwable;

/**
 * Mini runner: encontra as classes *Test em tests/Unit e tests/Integration, roda cada método
 * público "test*" em uma instância nova e conta passou/falhou/pulado.
 */
final class TestRunner
{
    private const SUITES = ['Unit', 'Integration'];

    private int $passed     = 0;
    private int $skipped    = 0;
    private int $assertions = 0;

    /** @var string[] */
    private array $failures = [];

    /** @var string[] */
    private array $skips = [];

    private ?string $filter;

    /**
     * @param string|null $filter só roda testes cujo "Classe::metodo" contenha este texto
     */
    public function __construct(?string $filter = null)
    {
        $this->filter = $filter;
    }

    /**
     * Roda tudo e devolve o código de saída (0 = tudo passou).
     */
    public function run(): int
    {
        foreach ($this->discover() as $class) {
            $this->runClass($class);
        }

        echo "\n";

        foreach ($this->skips as $skip) {
            echo 'PULADO  ' . $skip . "\n";
        }

        foreach ($this->failures as $index => $failure) {
            echo "\n" . ($index + 1) . ') FALHOU ' . $failure . "\n";
        }

        $failed = count($this->failures);
        $total  = $this->passed + $failed + $this->skipped;

        echo "\n" . sprintf(
            'PHP %s | Testes: %d | Passaram: %d | Falharam: %d | Pulados: %d | Asserções: %d',
            PHP_VERSION,
            $total,
            $this->passed,
            $failed,
            $this->skipped,
            $this->assertions
        ) . "\n";

        echo ($failed === 0 && $total > 0 ? 'OK' : 'FALHOU') . "\n";

        return $failed === 0 && $total > 0 ? 0 : 1;
    }

    /**
     * Indica se algum teste selecionado usa o banco de teste.
     */
    public function needsDatabase(): bool
    {
        foreach ($this->discover() as $class) {
            if (is_subclass_of($class, DatabaseTestCase::class) && $this->selectedMethods($class) !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return string[] nomes completos das classes de teste
     */
    private function discover(): array
    {
        $classes = [];

        foreach (self::SUITES as $suite) {
            $files = glob(dirname(__DIR__) . '/' . $suite . '/*Test.php') ?: [];
            sort($files);

            foreach ($files as $file) {
                $class = 'MovieStar\\Tests\\' . $suite . '\\' . basename($file, '.php');

                if (class_exists($class) && is_subclass_of($class, TestCase::class)
                    && !(new ReflectionClass($class))->isAbstract()) {
                    $classes[] = $class;
                }
            }
        }

        return $classes;
    }

    /**
     * @return string[]
     */
    private function selectedMethods(string $class): array
    {
        $methods = [];

        foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $name = $method->getName();

            if (strncmp($name, 'test', 4) !== 0 || $method->isStatic()) {
                continue;
            }

            if ($this->filter !== null && stripos($class . '::' . $name, $this->filter) === false) {
                continue;
            }

            $methods[] = $name;
        }

        return $methods;
    }

    private function runClass(string $class): void
    {
        $methods = $this->selectedMethods($class);

        if ($methods === []) {
            return;
        }

        $short = substr($class, strlen('MovieStar\\Tests\\'));
        echo $short . ' ';

        foreach ($methods as $method) {
            echo $this->runTest($class, $method);
        }

        echo "\n";
    }

    /**
     * @return string marcador de progresso: "." passou, "F" falhou, "S" pulado
     */
    private function runTest(string $class, string $method): string
    {
        $name = substr($class, strlen('MovieStar\\Tests\\')) . '::' . $method;

        /** @var TestCase $test */
        $test   = new $class();
        $error  = null;
        $marker = '.';

        try {
            $test->setUp();
            $test->$method();
        } catch (SkippedTestException $e) {
            $this->skips[] = $name . ' — ' . $e->getMessage();
            $marker        = 'S';
        } catch (AssertionFailedException $e) {
            $error = $e->getMessage() . $this->location($e);
        } catch (Throwable $e) {
            $error = 'exceção ' . get_class($e) . ': ' . $e->getMessage()
                . ' em ' . $e->getFile() . ':' . $e->getLine();
        }

        try {
            $test->tearDown();
        } catch (Throwable $e) {
            $error = ($error !== null ? $error . "\n   " : '') . 'tearDown: ' . get_class($e) . ': ' . $e->getMessage();
        }

        $this->assertions += $test->getAssertionCount();

        if ($error !== null) {
            $this->failures[] = $name . "\n   " . $error;

            return 'F';
        }

        if ($marker === 'S') {
            $this->skipped++;
        } else {
            $this->passed++;
        }

        return $marker;
    }

    /**
     * Linha do arquivo de teste onde a asserção falhou.
     */
    private function location(Throwable $e): string
    {
        foreach ($e->getTrace() as $frame) {
            $file = $frame['file'] ?? '';

            if ($file !== '' && strpos(str_replace('\\', '/', $file), '/tests/Support/') === false) {
                return "\n   em " . $file . ':' . ($frame['line'] ?? 0);
            }
        }

        return '';
    }
}
