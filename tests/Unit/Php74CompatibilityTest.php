<?php

declare(strict_types=1);

namespace MovieStar\Tests\Unit;

use MovieStar\Tests\Support\Php74CompatibilityScanner;
use MovieStar\Tests\Support\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * O projeto precisa rodar no PHP 7.4, mas só há PHP 8.x no ambiente: procura construções exclusivas
 * do PHP 8 em todos os .php do projeto (código da aplicação e dos testes).
 */
class Php74CompatibilityTest extends TestCase
{
    public function setUp(): void
    {
        if (PHP_VERSION_ID < 80000) {
            $this->skip('o scanner usa o tokenizer do PHP 8; no 7.4, "php -l" já acusa essas construções');
        }
    }

    public function testProjectHasNoPhp8OnlySyntax(): void
    {
        $problems = [];
        $files    = $this->projectFiles();

        foreach ($files as $file) {
            $problems = array_merge($problems, Php74CompatibilityScanner::scanFile($file));
        }

        $this->assertTrue(count($files) > 40, 'arquivos encontrados: ' . count($files));
        $this->assertSame([], $problems);
    }

    public function testScannerDetectsPhp8Constructs(): void
    {
        $samples = [
            'match'                 => '<?php $a = match ($x) { 1 => 2, default => 3 };',
            'nullsafe'              => '<?php $a = $b?->c;',
            'atributo'              => '<?php #[Attr] function f() {}',
            'promoção'              => '<?php class A { public function __construct(private int $x) {} }',
            'union no parâmetro'    => '<?php function f(int|string $x) {}',
            'union no retorno'      => '<?php function f(): int|false {}',
            'mixed'                 => '<?php function f(mixed $x) {}',
            'retorno static'        => '<?php class A { public function f(): static { return $this; } }',
            'argumento nomeado'     => '<?php htmlspecialchars($v, double_encode: false);',
            'vírgula final'         => "<?php function f(\$a,\n\$b,\n) {}",
            'catch sem variável'    => '<?php try {} catch (Exception) {}',
            'str_contains'          => '<?php str_contains($a, "b");',
            'new em valor padrão'   => '<?php function f($a = new A()) {}',
        ];

        foreach ($samples as $label => $code) {
            $this->assertTrue(Php74CompatibilityScanner::scanCode($code) !== [], $label);
        }
    }

    public function testScannerAcceptsPhp74Code(): void
    {
        $code = <<<'PHP'
<?php
class A extends B {
    private ?int $x = null;
    public function __construct(int $x, ?string $y = null, array $z = [1, 2]) { parent::__construct(); }
    public function f(callable $c, string ...$rest): ?self { return $this->g($a ? B::C : D, [E => 1]) ?? null; }
    public static function h(): array { return array_map(fn ($v) => $v * 2, [1]); }
}
try { f(); } catch (A | B $e) { }
$f = function () use ($a, $b): void { switch ($a) { case X: break; } };
$x = $cond ? CONSTANTE : OUTRA;
PHP;

        $this->assertSame([], Php74CompatibilityScanner::scanCode($code));
    }

    /**
     * @return string[]
     */
    private function projectFiles(): array
    {
        $root  = dirname(__DIR__, 2);
        $files = [];
        $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS));

        foreach ($items as $item) {
            $path     = $item->getPathname();
            $relative = str_replace('\\', '/', substr($path, strlen($root) + 1));

            if ($item->isFile() && substr($path, -4) === '.php'
                && preg_match('#^(\.git|\.claude|\.idea|vendor|img|logs)/#', $relative) !== 1) {
                $files[] = $path;
            }
        }

        sort($files);

        return $files;
    }
}
