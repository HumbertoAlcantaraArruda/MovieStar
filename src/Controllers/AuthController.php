<?php

declare(strict_types=1);

namespace MovieStar\Controllers;

use MovieStar\Core\Auth;
use MovieStar\Core\Session;
use MovieStar\Core\UserRules;
use MovieStar\DAO\UserDAO;
use MovieStar\Models\User;

/**
 * Processa cadastro, login e logout.
 */
class AuthController
{
    private UserDAO $userDAO;
    private Session $session;
    private Auth $auth;
    private string $baseUrl;

    public function __construct(UserDAO $userDAO, Session $session, Auth $auth, string $baseUrl)
    {
        $this->userDAO = $userDAO;
        $this->session = $session;
        $this->auth    = $auth;
        $this->baseUrl = $baseUrl;
    }

    /**
     * Trata o POST recebido (se houver) e redireciona ao final.
     * Usuário já logado só pode sair; qualquer outro acesso vai para a home.
     */
    public function handle(): void
    {
        $isPost   = $_SERVER['REQUEST_METHOD'] === 'POST';
        $isLogout = $isPost && ($_POST['type'] ?? '') === 'logout';

        if (!$isLogout) {
            $this->auth->requireGuest();
        }

        if (!$isPost) {
            return;
        }

        if (!$this->session->isValidCsrfToken($_POST['csrf_token'] ?? null)) {
            $this->redirect('auth.php', 'Requisição inválida. Tente novamente.', 'error');
        }

        switch ($_POST['type'] ?? '') {
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
        $name     = trim((string) ($_POST['name'] ?? ''));
        $lastname = trim((string) ($_POST['lastname'] ?? ''));
        $email    = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $confirm  = (string) ($_POST['confirmpassword'] ?? '');

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

        $this->session->login($id);
        $this->redirect('', 'Cadastro realizado com sucesso!');
    }

    private function login(): void
    {
        $email    = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        $user = $email !== '' ? $this->userDAO->findByEmail($email) : null;

        if ($user === null || !password_verify($password, $user->getPassword())) {
            $this->redirect('auth.php', 'E-mail ou senha incorretos.', 'error');
        }

        $this->session->login((int) $user->getId());
        $this->redirect('', 'Bem-vindo(a), ' . $user->getName() . '!');
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
