<?php

declare(strict_types=1);

namespace MovieStar\Controllers;

use MovieStar\Core\Session;
use MovieStar\DAO\UserDAO;
use MovieStar\Models\User;

/**
 * Processa cadastro, login e logout.
 */
class AuthController
{
    private const MIN_PASSWORD_LENGTH = 8;
    private const MAX_FIELD_LENGTH    = 100;

    private UserDAO $userDAO;
    private Session $session;
    private string $baseUrl;

    public function __construct(UserDAO $userDAO, Session $session, string $baseUrl)
    {
        $this->userDAO = $userDAO;
        $this->session = $session;
        $this->baseUrl = $baseUrl;
    }

    /**
     * Trata o POST recebido (se houver) e redireciona ao final.
     */
    public function handle(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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

        if (mb_strlen($name) > self::MAX_FIELD_LENGTH || mb_strlen($lastname) > self::MAX_FIELD_LENGTH) {
            $this->redirect('auth.php', 'Nome ou sobrenome muito longo.', 'error');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 191) {
            $this->redirect('auth.php', 'Informe um e-mail válido.', 'error');
        }

        if (strlen($password) < self::MIN_PASSWORD_LENGTH) {
            $this->redirect('auth.php', 'A senha deve ter ao menos ' . self::MIN_PASSWORD_LENGTH . ' caracteres.', 'error');
        }

        if ($password !== $confirm) {
            $this->redirect('auth.php', 'As senhas não conferem.', 'error');
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
