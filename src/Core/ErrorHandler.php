<?php

declare(strict_types=1);

namespace MovieStar\Core;

use ErrorException;
use Throwable;

/**
 * Tratamento centralizado de erros: converte warnings/notices em exceções, registra tudo em arquivo
 * e responde HTTP 500 com uma página genérica (ou com detalhes, quando debug está ligado).
 */
class ErrorHandler
{
    private const FATAL_TYPES = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR;

    private bool $debug;
    private string $logFile;
    private bool $handling = false;

    public function __construct(bool $debug, string $logFile)
    {
        $this->debug   = $debug;
        $this->logFile = $logFile;
    }

    public function register(): void
    {
        error_reporting(E_ALL);
        // Quem exibe o erro é esta classe; o PHP não imprime nada e error_log() cai no mesmo arquivo.
        ini_set('display_errors', '0');
        ini_set('log_errors', '0');
        ini_set('error_log', $this->logFile);

        // Buffer de saída: permite descartar HTML parcial se algo falhar no meio da página.
        ob_start();

        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);
        register_shutdown_function([$this, 'handleShutdown']);
    }

    /**
     * Converte warnings/notices em ErrorException (deprecations apenas vão para o log).
     */
    public function handleError(int $severity, string $message, string $file = '', int $line = 0): bool
    {
        // Respeita o operador @ (error_reporting zerado para o nível do erro).
        if ((error_reporting() & $severity) === 0) {
            return false;
        }

        if (($severity & (E_DEPRECATED | E_USER_DEPRECATED)) !== 0) {
            $this->log('Deprecated: ' . $message . ' em ' . $file . ':' . $line);

            return true;
        }

        throw new ErrorException($message, 0, $severity, $file, $line);
    }

    public function handleException(Throwable $e): void
    {
        if ($this->handling) {
            return;
        }

        $this->handling = true;
        $this->log(get_class($e) . ': ' . $e->getMessage() . ' em ' . $e->getFile() . ':' . $e->getLine()
            . "\n" . $e->getTraceAsString());
        $this->respond(get_class($e), $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString());
    }

    /**
     * Erros fatais (que não passam pelo handler de exceções).
     */
    public function handleShutdown(): void
    {
        $error = error_get_last();

        if ($this->handling || $error === null || ($error['type'] & self::FATAL_TYPES) === 0) {
            return;
        }

        $this->handling = true;
        $this->log('Fatal: ' . $error['message'] . ' em ' . $error['file'] . ':' . $error['line']);
        $this->respond('Erro fatal', $error['message'], $error['file'], $error['line'], '');
    }

    private function log(string $text): void
    {
        $dir = dirname($this->logFile);

        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        @error_log($text);
    }

    private function respond(string $type, string $message, string $file, int $line, string $trace): void
    {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }

        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=UTF-8');
        }

        try {
            echo $this->renderPage($type, $message, $file, $line, $trace);
        } catch (Throwable $ignored) {
            echo 'Erro interno do servidor.';
        }
    }

    /**
     * Página autossuficiente: não depende de header.php, sessão, banco nem de variáveis do bootstrap.
     */
    private function renderPage(string $type, string $message, string $file, int $line, string $trace): string
    {
        $e    = static function (string $v): string {
            return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
        };
        $home = rtrim(str_replace(DIRECTORY_SEPARATOR, '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/'))), '/') . '/';

        $html = '<!doctype html><html lang="pt-BR"><head><meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>Erro - MovieStar</title>'
            . '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.6.2/css/bootstrap.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">'
            . '</head><body class="bg-dark text-light"><div class="container py-5 text-center">'
            . '<h1 class="display-4">Algo deu errado</h1>'
            . '<p class="lead">Ocorreu um erro inesperado. Tente novamente em instantes.</p>'
            . '<a href="' . $e($home) . '" class="btn btn-warning">Voltar para a página inicial</a>';

        if ($this->debug) {
            $html .= '<div class="card bg-light text-dark text-left mt-5"><div class="card-body">'
                . '<h2 class="h5">' . $e($type) . '</h2>'
                . '<p>' . $e($message) . '</p>'
                . '<p class="small text-muted">' . $e($file) . ':' . $line . '</p>'
                . ($trace !== '' ? '<pre class="small mb-0">' . $e($trace) . '</pre>' : '')
                . '</div></div>';
        }

        return $html . '</div></body></html>';
    }
}
