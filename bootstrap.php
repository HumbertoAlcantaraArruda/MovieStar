<?php

declare(strict_types=1);

use MovieStar\Core\Auth;
use MovieStar\Core\Database;
use MovieStar\Core\Session;
use MovieStar\DAO\MovieDAO;
use MovieStar\DAO\UserDAO;

require_once __DIR__ . '/autoload.php';

$session = new Session();
$session->start();

$baseUrl = 'http://' . $_SERVER['SERVER_NAME'] . dirname($_SERVER['REQUEST_URI'] . '?') . '/';

$database = new Database(require __DIR__ . '/config/database.php');
$pdo      = $database->getConnection();

$userDAO     = new UserDAO($pdo);
$movieDAO    = new MovieDAO($pdo);
$currentUser = $session->getUserId() !== null ? $userDAO->findById($session->getUserId()) : null;

// Sessão apontando para usuário que não existe mais (ex.: apagado): trata como deslogado.
if ($currentUser === null && $session->getUserId() !== null) {
    $session->logout();
}

$auth  = new Auth($session, $currentUser, $baseUrl);
$flash = $session->pullFlash();
