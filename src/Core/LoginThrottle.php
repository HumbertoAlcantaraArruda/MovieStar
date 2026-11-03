<?php

declare(strict_types=1);

namespace MovieStar\Core;

use MovieStar\DAO\LoginAttemptDAO;

/**
 * Limite de tentativas de senha contra força bruta, guardado no banco (tabela login_attempts).
 *
 * Conta as tentativas dos últimos WINDOW_SECONDS por e-mail (protege a conta mesmo com IPs variados)
 * e por IP (freia um mesmo cliente testando muitos e-mails). Ficar no banco, e não na sessão, impede
 * que o atacante zere o contador apenas descartando o cookie.
 *
 * Limitações conhecidas: quem conhece um e-mail pode bloquear o login dessa conta por até
 * WINDOW_SECONDS (efeito colateral de todo bloqueio por conta); e o IP é o REMOTE_ADDR, então
 * atrás de proxy reverso todos os clientes compartilhariam o mesmo contador por IP.
 */
final class LoginThrottle
{
    public const MAX_ATTEMPTS_PER_EMAIL = 5;
    public const MAX_ATTEMPTS_PER_IP    = 20;
    public const WINDOW_SECONDS         = 900; // 15 minutos

    private const MAX_EMAIL_LENGTH = 191; // mesmo tamanho de users.email / login_attempts.email
    private const MAX_IP_LENGTH    = 45;  // IPv6 em texto

    private LoginAttemptDAO $attemptDAO;
    private string $ip;

    public function __construct(LoginAttemptDAO $attemptDAO, string $ip)
    {
        $this->attemptDAO = $attemptDAO;
        $this->ip         = substr($ip, 0, self::MAX_IP_LENGTH);
    }

    /**
     * Mensagem genérica de bloqueio (não revela se o e-mail existe).
     */
    public static function blockedMessage(): string
    {
        return 'Muitas tentativas. Aguarde ' . intdiv(self::WINDOW_SECONDS, 60) . ' minutos e tente novamente.';
    }

    /**
     * Registra uma tentativa de senha para $email e informa se ela pode prosseguir.
     * Chame ANTES de conferir a senha; se a senha estiver correta, chame clear().
     *
     * Já bloqueado: nada é gravado (o bloqueio expira sozinho). A recontagem depois do INSERT
     * barra rajadas de requisições paralelas que passariam juntas pela primeira checagem.
     */
    public function attempt(string $email): bool
    {
        $email = $this->normalize($email);

        if ($this->exceedsLimit($email, 1)) {
            return false;
        }

        $this->attemptDAO->deleteOlderThan(self::WINDOW_SECONDS);
        $this->attemptDAO->create($email, $this->ip);

        return !$this->exceedsLimit($email, 0);
    }

    /**
     * Zera as tentativas do e-mail após uma senha correta (o contador por IP não é zerado,
     * para que logar na própria conta não "limpe" as tentativas feitas contra outras).
     */
    public function clear(string $email): void
    {
        $this->attemptDAO->deleteByEmail($this->normalize($email));
    }

    /**
     * @param int $pending tentativas ainda não gravadas a somar à contagem
     */
    private function exceedsLimit(string $email, int $pending): bool
    {
        return $this->attemptDAO->countRecentByEmail($email, self::WINDOW_SECONDS) + $pending > self::MAX_ATTEMPTS_PER_EMAIL
            || $this->attemptDAO->countRecentByIp($this->ip, self::WINDOW_SECONDS) + $pending > self::MAX_ATTEMPTS_PER_IP;
    }

    private function normalize(string $email): string
    {
        return mb_substr(mb_strtolower(trim($email), 'UTF-8'), 0, self::MAX_EMAIL_LENGTH, 'UTF-8');
    }
}
