<?php

declare(strict_types=1);

namespace MovieStar\Tests\Support;

use RuntimeException;

/**
 * Executa um script de tests/cgi/ em um processo php-cgi separado (mesma versão do PHP do runner),
 * simulando uma requisição sem servidor web: query string, campos POST e arquivos chegam ao PHP
 * como em uma requisição real ($_GET, $_POST, $_FILES, filter_input(), is_uploaded_file()).
 *
 * Um processo por requisição: os controllers terminam com exit() após redirecionar.
 */
final class CgiClient
{
    private const RESULT_MARKER = '@@MOVIESTAR_TEST_RESULT@@';

    private function __construct()
    {
    }

    /**
     * Caminho do php-cgi ao lado do PHP em execução, ou null se não existir.
     */
    public static function binary(): ?string
    {
        $file = dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . (PHP_OS_FAMILY === 'Windows' ? 'php-cgi.exe' : 'php-cgi');

        return is_file($file) ? $file : null;
    }

    /**
     * @param string $script  nome do script em tests/cgi/ (ex.: "controller.php")
     * @param array<string, mixed> $spec  dados para o script (JSON na variável de ambiente MOVIESTAR_TEST_SPEC)
     * @param array<string, mixed> $request method, query, post, files (nome => [filename, content, type]), remoteAddr
     * @return array{headers: string[], location: ?string, body: string, result: array<string, mixed>}
     */
    public static function run(string $script, array $spec, array $request = []): array
    {
        $binary = self::binary();

        if ($binary === null) {
            throw new SkippedTestException('php-cgi não encontrado ao lado de ' . PHP_BINARY);
        }

        $method   = (string) ($request['method'] ?? 'POST');
        $boundary = '----MovieStarTest' . bin2hex(random_bytes(8));
        $body     = $method === 'POST'
            ? self::multipartBody($boundary, $request['post'] ?? [], $request['files'] ?? [])
            : '';

        $tmp = TempDirectory::path('cgi');

        $env = array_merge(getenv(), [
            'MOVIESTAR_TEST_RUN'  => '1',
            'MOVIESTAR_TEST_SPEC' => (string) json_encode($spec),
            'REDIRECT_STATUS'     => '200',
            'GATEWAY_INTERFACE'   => 'CGI/1.1',
            'SERVER_PROTOCOL'     => 'HTTP/1.1',
            'REQUEST_METHOD'      => $method,
            'SCRIPT_FILENAME'     => dirname(__DIR__) . DIRECTORY_SEPARATOR . 'cgi' . DIRECTORY_SEPARATOR . $script,
            'SCRIPT_NAME'         => '/MovieStar/' . $script,
            'QUERY_STRING'        => http_build_query($request['query'] ?? []),
            'REMOTE_ADDR'         => (string) ($request['remoteAddr'] ?? '127.0.0.1'),
            'CONTENT_TYPE'        => $method === 'POST' ? 'multipart/form-data; boundary=' . $boundary : '',
            'CONTENT_LENGTH'      => (string) strlen($body),
        ]);

        $command = [
            $binary,
            '-d', 'xdebug.mode=off',
            '-d', 'display_errors=0',
            '-d', 'log_errors=1',
            '-d', 'error_log=' . $tmp . DIRECTORY_SEPARATOR . 'php-errors.log',
            '-d', 'session.save_path=' . TempDirectory::path('sessions'),
            '-d', 'session.use_cookies=0',
            '-d', 'session.use_strict_mode=0',
        ];

        $stderrFile = $tmp . DIRECTORY_SEPARATOR . 'stderr.log';
        $process    = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['file', $stderrFile, 'a']], $pipes, null, $env);

        if (!is_resource($process)) {
            throw new RuntimeException('Não foi possível iniciar o php-cgi.');
        }

        fwrite($pipes[0], $body);
        fclose($pipes[0]);
        $output = (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        proc_close($process);

        return self::parse($output);
    }

    /**
     * @param array<string, mixed> $post
     * @param array<string, array{0: string, 1: string, 2?: string}> $files
     */
    private static function multipartBody(string $boundary, array $post, array $files): string
    {
        $body = '';

        foreach (self::flatten($post) as [$name, $value]) {
            $body .= '--' . $boundary . "\r\n"
                . 'Content-Disposition: form-data; name="' . $name . '"' . "\r\n\r\n"
                . $value . "\r\n";
        }

        foreach ($files as $name => $file) {
            $body .= '--' . $boundary . "\r\n"
                . 'Content-Disposition: form-data; name="' . $name . '"; filename="' . $file[0] . '"' . "\r\n"
                . 'Content-Type: ' . ($file[2] ?? 'application/octet-stream') . "\r\n\r\n"
                . $file[1] . "\r\n";
        }

        return $body . '--' . $boundary . "--\r\n";
    }

    /**
     * Campos aninhados viram nomes "campo[chave]" (como um formulário com name="campo[]").
     *
     * @param array<mixed> $data
     * @return array<int, array{0: string, 1: string}>
     */
    private static function flatten(array $data, string $prefix = ''): array
    {
        $fields = [];

        foreach ($data as $key => $value) {
            $name = $prefix === '' ? (string) $key : $prefix . '[' . $key . ']';

            if (is_array($value)) {
                $fields = array_merge($fields, self::flatten($value, $name));
            } else {
                $fields[] = [$name, (string) $value];
            }
        }

        return $fields;
    }

    /**
     * @return array{headers: string[], location: ?string, body: string, result: array<string, mixed>}
     */
    private static function parse(string $output): array
    {
        $split   = strpos($output, "\r\n\r\n");
        $head    = $split === false ? '' : substr($output, 0, $split);
        $body    = $split === false ? $output : substr($output, $split + 4);
        $headers = $head === '' ? [] : explode("\r\n", $head);

        $location = null;

        foreach ($headers as $header) {
            if (stripos($header, 'Location:') === 0) {
                $location = trim(substr($header, 9));
            }
        }

        $marker = strrpos($body, self::RESULT_MARKER);
        $result = $marker === false ? null : json_decode(substr($body, $marker + strlen(self::RESULT_MARKER)), true);

        if (!is_array($result)) {
            throw new RuntimeException('Resposta inesperada do php-cgi: ' . substr($output, 0, 2000));
        }

        return [
            'headers'  => $headers,
            'location' => $location,
            'body'     => $marker === false ? $body : substr($body, 0, $marker),
            'result'   => $result,
        ];
    }

    public static function resultMarker(): string
    {
        return self::RESULT_MARKER;
    }
}
