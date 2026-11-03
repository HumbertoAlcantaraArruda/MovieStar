<?php

declare(strict_types=1);

namespace MovieStar\Controllers;

use MovieStar\Core\Auth;
use MovieStar\Core\LoginThrottle;
use MovieStar\Core\Session;
use MovieStar\Core\UserRules;
use MovieStar\DAO\UserDAO;
use MovieStar\Models\User;

/**
 * Processa cadastro, login e logout.
 */
class AuthController
{
    /**
     * Hash bcrypt de uma senha aleatória descartada: quando o e-mail não existe, a senha é conferida
     * contra ele para que o tempo de resposta não revele quais e-mails estão cadastrados.
     */
    private const DUMMY_PASSWORD_HASH = '$2y$10$o2uSAW5K.psVUFKPXkU22.u.qgNr./Ddm6NbMIC3UzRtIBNMw2AAu';

    private UserDAO $userDAO;
    private Session $session;
    private Auth $auth;
    private LoginThrottle $loginThrottle;
    private string $baseUrl;

    public function __construct(
        UserDAO $userDAO,
        Session $session,
        Auth $auth,
        LoginThrottle $loginThrottle,
        string $baseUrl
    ) {
        $this->userDAO       = $userDAO;
        $this->session       = $session;
        $this->auth          = $auth;
        $this->loginThrottle = $loginThrottle;
        $this->baseUrl       = $baseUrl;
    }

    /**
     * Trata o POST recebido (se houver) e redireciona ao final.
     * Usuário já logado só pode sair; qualquer outro acesso vai para a home.
     */
    public function handle(): void
    {
        $isPost   = $_SERVER['REQUEST_METHOD'] === 'POST';
        $type     = $isPost ? $this->postString('type') : '';
        $isLogout = $type === 'logout';

        if (!$isLogout) {
            $this->auth->requireGuest();
        }

        if (!$isPost) {
            return;
        }

        if (!$this->session->isValidCsrfToken($_POST['csrf_token'] ?? null)) {
            // Logout recusado vai para a home: em auth.php o usuário ainda logado seria redirecionado
            // de novo e a mensagem se perderia no caminho.
            $this->redirect($isLogout ? '' : 'auth.php', 'Requisição inválida. Tente novamente.', 'error');
        }

        switch ($type) {
            case 'register':
                $this->register();
                break;
            case 'login':
                $this->login();
                break;
            case 'logout':
                $this->session->logout();
                $this->redirect('', 'Você saiu da sua conta.');
                break;
        }
    }

    private function register(): void
    {
        $name     = $this->postString('name');
        $lastname = $this->postString('lastname');
        $email    = $this->postString('email');
        // Senhas não passam por trim(): espaços fazem parte da senha.
        $password = $this->rawPostString('password');
        $confirm  = $this->rawPostString('confirmpassword');

        if ($name === '' || $lastname === '' || $email === '' || $password === '') {
            $this->redirect('auth.php', 'Preencha todos os campos.', 'error');
        }

        $nameError = UserRules::nameError($name, $lastname);

        if ($nameError !== null) {
            $this->redirect('auth.php', $nameError, 'error');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 191) {
            $this->redirect('auth.php', 'Informe um e-mail válido.', 'error');
        }

        $passwordError = UserRules::passwordError($password, $confirm);

        if ($passwordError !== null) {
            $this->redirect('auth.php', $passwordError, 'error');
        }

        if ($this->userDAO->findByEmail($email) !== null) {
            $this->redirect('auth.php', 'Este e-mail já está cadastrado.', 'error');
        }

        $user = (new User())
            ->setName($name)
            ->setLastname($lastname)
            ->setEmail($email)
            ->setPassword(password_hash($password, PASSWORD_DEFAULT))
            ->setToken(bin2hex(random_bytes(25)));

        $id = $this->userDAO->create($user);

        $this->session->login($id, (string) $user->getToken());
        $this->redirect('', 'Cadastro realizado com sucesso!');
    }

    private function login(): void
    {
        $email    = $this->postString('email');
        $password = $this->rawPostString('password');

        // Checado antes de qualquer verificação de senha; a mensagem não revela se o e-mail existe.
        if (!$this->loginThrottle->attempt($email)) {
            $this->redirect('auth.php', LoginThrottle::blockedMessage(), 'error');
        }

        $user = $email !== '' ? $this->userDAO->findByEmail($email) : null;
        $hash = $user !== null ? $user->getPassword() : self::DUMMY_PASSWORD_HASH;

        if (!password_verify($password, $hash) || $user === null) {
            $this->redirect('auth.php', 'E-mail ou senha incorretos.', 'error');
        }

        $this->loginThrottle->clear($email);
        $this->session->login((int) $user->getId(), (string) $user->getToken());
        $this->redirect('', 'Bem-vindo(a), ' . $user->getName() . '!');
    }

    /**
     * Valor de $_POST como string aparada (arrays, ausentes e UTF-8 inválido viram '').
     */
    private function postString(string $key): string
    {
        return trim($this->rawPostString($key));
    }

    private function rawPostString(string $key): string
    {
        $value = $_POST[$key] ?? '';

        return is_string($value) && mb_check_encoding($value, 'UTF-8') ? $value : '';
    }

    /**
     * @return never
     */
    private function redirect(string $path, string $message, string $type = 'success'): void
    {
        $this->session->setFlash($message, $type);
        header('Location: ' . $this->baseUrl . $path);
        exit;
    }
}
