<?php

declare(strict_types=1);

use MovieStar\Core\Auth;
use MovieStar\Core\Database;
use MovieStar\Core\ErrorHandler;
use MovieStar\Core\LoginThrottle;
use MovieStar\Core\Session;
use MovieStar\DAO\LoginAttemptDAO;
use MovieStar\DAO\MovieDAO;
use MovieStar\DAO\ReviewDAO;
use MovieStar\DAO\UserDAO;

require_once __DIR__ . '/autoload.php';

// Caminho base da aplicação (ex.: "/MovieStar/"), tirado do script em execução (definido pelo servidor).
// Não usa o Host (SERVER_NAME) nem REQUEST_URI, que vêm do cliente: evita Host header injection em
// links/redirecionamentos e URLs quebradas por "/" na query ou PATH_INFO. Relativo à raiz, vale em HTTP e HTTPS.
// Começa sempre com uma única "/" (nunca "//host", que o navegador trataria como outro site).
$scriptDir = trim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');
$baseUrl   = '/' . ($scriptDir !== '' ? implode('/', array_map('rawurlencode', explode('/', $scriptDir))) . '/' : '');

// Sem config/app.php, o modo é produção (debug desligado).
$appConfig = is_file(__DIR__ . '/config/app.php') ? require __DIR__ . '/config/app.php' : [];
(new ErrorHandler(is_array($appConfig) && ($appConfig['debug'] ?? false) === true, __DIR__ . '/logs/app.log', $baseUrl))->register();

$session = new Session();
$session->start($baseUrl);

$database = new Database(require __DIR__ . '/config/database.php');
$pdo      = $database->getConnection();

$userDAO     = new UserDAO($pdo);
$movieDAO    = new MovieDAO($pdo);
$reviewDAO   = new ReviewDAO($pdo);
$currentUser = $session->getUserId() !== null ? $userDAO->findById($session->getUserId()) : null;

// Sessão apontando para usuário que não existe mais (ex.: apagado) ou aberta antes de uma troca de senha
// (o token do usuário mudou): trata como deslogado.
if ($session->getUserId() !== null
    && ($currentUser === null || !$session->isAuthTokenValid($currentUser->getToken()))) {
    $session->logout();
    $currentUser = null;
}

$auth  = new Auth($session, $currentUser, $baseUrl);
$flash = $session->pullFlash();

// Limite de tentativas de senha (login e troca de senha); o IP é o da conexão, nunca um cabeçalho do cliente.
$loginThrottle = new LoginThrottle(new LoginAttemptDAO($pdo), (string) ($_SERVER['REMOTE_ADDR'] ?? ''));
