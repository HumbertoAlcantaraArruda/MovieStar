<?php
declare(strict_types=1);

use MovieStar\Controllers\ProfileController;
use MovieStar\Core\ImageUploader;

require_once __DIR__ . '/bootstrap.php';
/**
 * @var \MovieStar\DAO\UserDAO $userDAO
 * @var \MovieStar\DAO\MovieDAO $movieDAO
 * @var \MovieStar\DAO\ReviewDAO $reviewDAO
 * @var \MovieStar\Core\Session $session
 * @var \MovieStar\Core\Auth $auth
 * @var \MovieStar\Core\LoginThrottle $loginThrottle
 * @var string $baseUrl
 */

$imageUploader = new ImageUploader(__DIR__ . '/img/users');

// Responde 404 (sem redirecionar) quando o id é inválido ou o usuário não existe.
$page = (new ProfileController($userDAO, $movieDAO, $reviewDAO, $imageUploader, $session, $auth, $loginThrottle, $baseUrl))->handleShow();

require_once __DIR__ . '/templates/header.php';
?>
    <div id="main-container" class="container-fluid">
<?php if ($page === null): ?>
        <div class="text-center py-5">
            <i class="fas fa-user-slash fa-3x mb-3 text-white-50"></i>
            <h1 class="h2">Usuário não encontrado</h1>
            <p class="text-white-50">O perfil que você procura não existe ou foi removido.</p>
            <a href="<?= $e($baseUrl) ?>" class="btn btn-light">Voltar para a home</a>
        </div>
<?php else:
    $profile = $page['profile'];
    $movies  = $page['movies'];
?>
        <div class="row mb-4">
            <div class="col-12 col-md-3 col-lg-2 mb-3 text-center text-md-left">
                <img src="<?= $e($baseUrl) ?>img/users/<?= $e($profile->getImageOrDefault()) ?>"
                     class="img-fluid rounded-circle" style="max-width: 160px;"
                     alt="Foto de <?= $e($profile->getFullName()) ?>">
            </div>
            <div class="col-12 col-md-9 col-lg-10">
                <h1><?= $e($profile->getFullName()) ?></h1>
<?php if ($page['isOwner']): ?>
                <p>
                    <a href="<?= $e($baseUrl) ?>editprofile.php" class="btn btn-sm btn-light">
                        <i class="fas fa-user-edit"></i> Editar perfil
                    </a>
                </p>
<?php endif; ?>
<?php if ($profile->getBio() !== null && trim($profile->getBio()) !== ''): ?>
                <p><?= nl2br($e($profile->getBio())) ?></p>
<?php else: ?>
                <p class="text-white-50">Este usuário ainda não escreveu uma bio.</p>
<?php endif; ?>
            </div>
        </div>

        <h2 class="section-title">Filmes de <?= $e($profile->getName()) ?></h2>
<?php if (count($movies) === 0): ?>
        <p class="text-white-50">Nenhum filme cadastrado ainda.</p>
<?php else: ?>
        <div class="row">
<?php foreach ($movies as $movie): ?>
<?php $rating = $page['ratings'][(int) $movie->getId()] ?? null; ?>
<?php require __DIR__ . '/templates/movie_card.php'; ?>
<?php endforeach; ?>
        </div>
<?php $pagination = $page['pagination']; $paginationParams = ['id' => (int) $profile->getId()]; include __DIR__ . '/templates/pagination.php'; ?>
<?php endif; ?>
<?php endif; ?>
    </div>

<?php
require_once __DIR__ . '/templates/footer.php';
