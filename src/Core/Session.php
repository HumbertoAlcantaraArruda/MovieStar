<?php

declare(strict_types=1);

namespace MovieStar\Core;

/**
 * Encapsula $_SESSION: autenticação, mensagens flash e token CSRF.
 */
class Session
{
    private const KEY_USER_ID    = 'user_id';
    private const KEY_AUTH_TOKEN = 'auth_token';
    private const KEY_FLASH      = 'flash';
    private const KEY_CSRF       = 'csrf_token';
    private const KEY_OLD_INPUT  = 'old_input';

    /** Nome próprio do cookie: não colide com o PHPSESSID de outras aplicações no mesmo host. */
    private const SESSION_NAME = 'MOVIESTARSESSID';

    /**
     * Inicia a sessão com o cookie endurecido (deve ser chamada antes de qualquer saída).
     *
     * @param string $cookiePath caminho da aplicação (ex.: "/MovieStar/"); o cookie só é enviado para ele
     */
    public function start(string $cookiePath = '/'): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }

        // Só aceita ids criados pelo próprio servidor (contra fixação de sessão) e só via cookie, nunca na URL.
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');

        session_name(self::SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => $cookiePath,
            'domain'   => '',
            'secure'   => self::isHttps(),
            'httponly' => true,
            // Lax: o cookie não vai em POST de outros sites, mas links externos para o MovieStar continuam logados.
            'samesite' => 'Lax',
        ]);

        session_start();
    }

    /**
     * Autentica a sessão (novo id de sessão, contra fixação).
     *
     * @param string $authToken token atual do usuário (users.token); quando ele muda (troca de senha),
     *                          as sessões abertas com o token antigo deixam de valer (isAuthTokenValid())
     */
    public function login(int $userId, string $authToken): void
    {
        session_regenerate_id(true);
        $_SESSION[self::KEY_USER_ID]    = $userId;
        $_SESSION[self::KEY_AUTH_TOKEN] = $authToken;

        // Novo token CSRF a cada autenticação: um token obtido antes do login não vale depois dele.
        unset($_SESSION[self::KEY_CSRF]);
    }

    /**
     * Indica se a sessão foi aberta com o token atual do usuário (falso após troca de senha em outra sessão).
     */
    public function isAuthTokenValid(?string $userToken): bool
    {
        $token = $_SESSION[self::KEY_AUTH_TOKEN] ?? null;

        return is_string($token) && hash_equals((string) $userToken, $token);
    }

    public function logout(): void
    {
        $_SESSION = [];
        session_regenerate_id(true);
    }

    public function getUserId(): ?int
    {
        return isset($_SESSION[self::KEY_USER_ID]) ? (int) $_SESSION[self::KEY_USER_ID] : null;
    }

    public function setFlash(string $message, string $type = 'success'): void
    {
        $_SESSION[self::KEY_FLASH] = ['msg' => $message, 'type' => $type];
    }

    /**
     * Retorna e remove a mensagem flash.
     *
     * @return array{msg: string, type: string}|null
     */
    public function pullFlash(): ?array
    {
        $flash = $_SESSION[self::KEY_FLASH] ?? null;
        unset($_SESSION[self::KEY_FLASH]);

        return $flash;
    }

    /**
     * Guarda os valores digitados em um formulário para repopulá-lo após um erro de validação
     * (um único formulário por vez). Só strings; senhas e arquivos nunca devem ser passados.
     *
     * @param array<string, mixed> $data
     */
    public function setOldInput(string $form, array $data): void
    {
        $clean = [];

        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $clean[(string) $key] = $value;
            }
        }

        $_SESSION[self::KEY_OLD_INPUT] = ['form' => $form, 'data' => $clean];
    }

    /**
     * Retorna e remove os valores guardados, se forem do formulário pedido (senão, array vazio e nada é removido).
     *
     * @return array<string, string>
     */
    public function pullOldInput(string $form): array
    {
        $old = $_SESSION[self::KEY_OLD_INPUT] ?? null;

        // Só consome o que é deste formulário: uma página com dois formulários (auth.php) consulta os dois.
        if (is_array($old) && ($old['form'] ?? null) === $form && is_array($old['data'] ?? null)) {
            unset($_SESSION[self::KEY_OLD_INPUT]);

            return $old['data'];
        }

        return [];
    }

    public function csrfToken(): string
    {
        if (empty($_SESSION[self::KEY_CSRF])) {
            $_SESSION[self::KEY_CSRF] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::KEY_CSRF];
    }

    /**
     * Confere o token enviado pelo formulário. Recebe o valor bruto (ex.: $_POST['csrf_token'] ?? null):
     * qualquer coisa que não seja string (null, array de "csrf_token[]=x"...) é simplesmente inválida.
     *
     * @param mixed $token
     */
    public function isValidCsrfToken($token): bool
    {
        return is_string($token)
            && $token !== ''
            && isset($_SESSION[self::KEY_CSRF])
            && is_string($_SESSION[self::KEY_CSRF])
            && hash_equals($_SESSION[self::KEY_CSRF], $token);
    }

    /**
     * HTTPS direto no Apache. Atrás de proxy/terminador TLS, o servidor precisa repassar HTTPS=on.
     */
    private static function isHttps(): bool
    {
        $https = $_SERVER['HTTPS'] ?? '';

        return (is_string($https) && $https !== '' && strtolower($https) !== 'off')
            || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
    }
}
