<?php

declare(strict_types=1);

/**
 * Processo filho (php-cgi) que executa um controller da aplicação contra o banco de teste.
 * Iniciado apenas pelo runner via Support\CgiClient; o resultado é a sessão (flash, old input,
 * usuário logado) e o cabeçalho Location gravados até o exit() do redirecionamento.
 *
 * Spec: controller (auth|movie_new|movie_edit|dashboard|review|profile_edit), userId (logado ou null),
 *       csrf (token guardado na sessão), uploadDir, raceOnEmail (simula a corrida do cadastro).
 */

use MovieStar\Controllers\AuthController;
use MovieStar\Controllers\MovieController;
use MovieStar\Controllers\ProfileController;
use MovieStar\Controllers\ReviewController;
use MovieStar\Core\Auth;
use MovieStar\Core\ImageUploader;
use MovieStar\Core\LoginThrottle;
use MovieStar\Core\Session;
use MovieStar\DAO\LoginAttemptDAO;
use MovieStar\DAO\MovieDAO;
use MovieStar\DAO\ReviewDAO;
use MovieStar\DAO\UserDAO;
use MovieStar\Models\User;
use MovieStar\Tests\Support\ChildProcess;
use MovieStar\Tests\Support\TestDatabase;

require dirname(__DIR__) . '/bootstrap.php';

$spec = ChildProcess::start();

session_start();
$_SESSION = [];

if (isset($spec['csrf']) && is_string($spec['csrf'])) {
    $_SESSION['csrf_token'] = $spec['csrf'];
}

$pdo       = TestDatabase::pdo();
$baseUrl   = '/MovieStar/';
$movieDAO  = new MovieDAO($pdo);
$reviewDAO = new ReviewDAO($pdo);
$userDAO   = new UserDAO($pdo);

if (!empty($spec['raceOnEmail'])) {
    // Corrida de dois cadastros simultâneos: a checagem prévia não vê o e-mail (o outro INSERT
    // ainda não aconteceu), mas o INSERT esbarra na UNIQUE.
    $userDAO = new class ($pdo) extends UserDAO {
        public function findByEmail(string $email): ?User
        {
            return null;
        }
    };
}

$session     = new Session();
$currentUser = isset($spec['userId']) ? $userDAO->findById((int) $spec['userId']) : null;

if ($currentUser !== null) {
    $_SESSION['user_id'] = $currentUser->getId();
}

$auth          = new Auth($session, $currentUser, $baseUrl);
$loginThrottle = new LoginThrottle(new LoginAttemptDAO($pdo), (string) ($_SERVER['REMOTE_ADDR'] ?? ''));
$uploadDir     = (string) ($spec['uploadDir'] ?? sys_get_temp_dir());

switch ($spec['controller'] ?? '') {
    case 'auth':
        (new AuthController($userDAO, $session, $auth, $loginThrottle, $baseUrl))->handle();
        break;
    case 'movie_new':
        (new MovieController($movieDAO, new ImageUploader($uploadDir), $session, $auth, $baseUrl))->handle();
        break;
    case 'movie_edit':
        (new MovieController($movieDAO, new ImageUploader($uploadDir), $session, $auth, $baseUrl))->handleEdit();
        break;
    case 'dashboard':
        (new MovieController($movieDAO, new ImageUploader($uploadDir), $session, $auth, $baseUrl))->handleDashboard();
        break;
    case 'review':
        (new ReviewController($movieDAO, $reviewDAO, $session, $auth, $baseUrl))->handle();
        break;
    case 'profile_edit':
        (new ProfileController($userDAO, $movieDAO, $reviewDAO, new ImageUploader($uploadDir), $session, $auth, $loginThrottle, $baseUrl))->handleEdit();
        break;
    default:
        throw new InvalidArgumentException('Controller desconhecido.');
}

// Só chega aqui se o controller não redirecionou (ex.: GET).
ChildProcess::set('returned', true);
