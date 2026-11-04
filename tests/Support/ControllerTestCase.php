<?php

declare(strict_types=1);

namespace MovieStar\Tests\Support;

/**
 * Base dos testes de controller: cada requisição roda em um php-cgi separado (tests/cgi/controller.php)
 * contra o banco de teste, e o teste confere a mensagem flash, o redirecionamento e o banco.
 */
abstract class ControllerTestCase extends DatabaseTestCase
{
    protected const CSRF = 'csrf-token-de-teste-0123456789abcdef';

    protected string $uploadDir;

    public function setUp(): void
    {
        if (CgiClient::binary() === null) {
            $this->skip('php-cgi não encontrado ao lado de ' . PHP_BINARY);
        }

        parent::setUp();
        $this->uploadDir = TempDirectory::fresh('controller-upload');
    }

    /**
     * Envia um POST ao controller (com o token CSRF correto, salvo se $post já trouxer csrf_token).
     *
     * @param array<string, mixed> $post
     * @param array<string, mixed> $options userId, query, files, spec (extras do spec), remoteAddr, method
     * @return array{location: ?string, flash: ?array{msg: string, type: string}, session: array<string, mixed>, returned: bool}
     */
    protected function post(string $controller, array $post, array $options = []): array
    {
        if (!array_key_exists('csrf_token', $post)) {
            $post['csrf_token'] = self::CSRF;
        }

        if ($post['csrf_token'] === null) {
            unset($post['csrf_token']);
        }

        $spec = array_merge([
            'controller' => $controller,
            'csrf'       => self::CSRF,
            'userId'     => $options['userId'] ?? null,
            'uploadDir'  => $this->uploadDir,
        ], $options['spec'] ?? []);

        $response = CgiClient::run('controller.php', $spec, [
            'method'     => $options['method'] ?? 'POST',
            'query'      => $options['query'] ?? [],
            'post'       => $post,
            'files'      => $options['files'] ?? [],
            'remoteAddr' => $options['remoteAddr'] ?? '127.0.0.1',
        ]);

        $result = $response['result'];

        $this->assertNull($result['exception'], 'sem exceção no controller');
        $this->assertSame([], $result['errors'], 'sem warnings/notices no controller');
        $this->assertSame('', $result['output'], 'controller não imprime nada antes do redirecionamento');

        $session = is_array($result['session'] ?? null) ? $result['session'] : [];

        return [
            'location' => $response['location'],
            'flash'    => isset($session['flash']) && is_array($session['flash']) ? $session['flash'] : null,
            'session'  => $session,
            'returned' => (bool) $result['returned'],
        ];
    }

    /**
     * Confere redirecionamento + mensagem flash.
     *
     * @param array{location: ?string, flash: ?array{msg: string, type: string}} $result
     */
    protected function assertRedirect(array $result, string $path, string $message, string $type = 'error'): void
    {
        $this->assertSame('/MovieStar/' . $path, $result['location'], 'redirecionamento');
        $this->assertSame(['msg' => $message, 'type' => $type], $result['flash'], 'mensagem flash');
    }
}
