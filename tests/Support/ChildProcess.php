<?php

declare(strict_types=1);

namespace MovieStar\Tests\Support;

use Throwable;

/**
 * Lado "filho" do CgiClient: registra warnings/exceções e, ao terminar o processo (inclusive
 * por exit() depois de um redirecionamento), imprime um JSON com o resultado para o runner.
 */
final class ChildProcess
{
    /** @var array<string, mixed> */
    private static array $result = [
        'returned'  => false,
        'errors'    => [],
        'exception' => null,
    ];

    private function __construct()
    {
    }

    /**
     * Prepara o processo e devolve os dados enviados pelo runner.
     *
     * @return array<string, mixed>
     */
    public static function start(): array
    {
        ob_start();

        set_error_handler(static function (int $severity, string $message, string $file = '', int $line = 0): bool {
            self::$result['errors'][] = $message . ' em ' . $file . ':' . $line;

            return true;
        });

        set_exception_handler(static function (Throwable $e): void {
            self::$result['exception'] = get_class($e) . ': ' . $e->getMessage() . ' em ' . $e->getFile() . ':' . $e->getLine();
        });

        register_shutdown_function([self::class, 'finish']);

        $spec = json_decode((string) getenv('MOVIESTAR_TEST_SPEC'), true);

        return is_array($spec) ? $spec : [];
    }

    /**
     * @param mixed $value
     */
    public static function set(string $key, $value): void
    {
        self::$result[$key] = $value;
    }

    /**
     * Executado no fim do processo: anexa sessão e erro fatal (se houver) e imprime o resultado.
     */
    public static function finish(): void
    {
        $error = error_get_last();

        if ($error !== null && ($error['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR)) !== 0) {
            self::$result['exception'] = 'Fatal: ' . $error['message'] . ' em ' . $error['file'] . ':' . $error['line'];
        }

        self::$result['session'] = isset($_SESSION) && is_array($_SESSION) ? $_SESSION : [];

        $output = '';

        while (ob_get_level() > 0) {
            $output .= (string) ob_get_clean();
        }

        self::$result['output'] = $output;

        echo CgiClient::resultMarker() . json_encode(self::$result, JSON_PARTIAL_OUTPUT_ON_ERROR);
    }
}
