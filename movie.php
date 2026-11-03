<?php
declare(strict_types=1);

use MovieStar\Controllers\MoviePageController;

require_once __DIR__ . '/bootstrap.php';
/**
 * @var \MovieStar\DAO\MovieDAO $movieDAO
 * @var \MovieStar\DAO\UserDAO $userDAO
 * @var \MovieStar\Core\Auth $auth
 * @var string $baseUrl
 */

// Responde 404 (sem redirecionar) quando o id é inválido ou o filme não existe.
$page = (new MoviePageController($movieDAO, $userDAO, $auth))->handleShow();

require_once __DIR__ . '/templates/header.php';
?>
<div id="main-container" class="container-fluid">
<?php if ($page === null): ?>
    <div class="text-center py-5">
        <i class="fas fa-film fa-3x mb-3 text-white-50"></i>
        <h1 class="h2">Filme não encontrado</h1>
        <p class="text-white-50">O filme que você procura não existe ou foi removido.</p>
        <a href="<?= $e($baseUrl) ?>" class="btn btn-light">Voltar para a home</a>
    </div>
<?php else:
    $movie = $page['movie'];
    $author = $page['author'];
    $trailerUrl = $page['trailerUrl'];
?>
    <div class="row movie-page">
        <div class="col-12 col-md-4 col-lg-3 mb-4 text-center text-md-left">
            <img src="<?= $e($baseUrl) ?>img/movies/<?= $e($movie->getImageOrDefault()) ?>"
                 class="img-fluid rounded movie-page-cover" alt="Capa de <?= $e($movie->getTitle()) ?>">
        </div>
        <div class="col-12 col-md-8 col-lg-9">
            <h1><?= $e($movie->getTitle()) ?></h1>
            <p class="text-white-50">
<?php if ($movie->getCategory() !== null && $movie->getCategory() !== ''): ?>
                <span class="badge badge-secondary"><?= $e($movie->getCategory()) ?></span>
<?php endif; ?>
<?php if ($movie->getLength() !== null && $movie->getLength() !== ''): ?>
                <span class="ml-2"><i class="far fa-clock"></i> <?= $e($movie->getLength()) ?></span>
<?php endif; ?>
            </p>
<?php if ($author !== null): ?>
            <p class="text-white-50 small">Cadastrado por <?= $e(trim($author->getName() . ' ' . $author->getLastname())) ?></p>
<?php endif; ?>
<?php if ($page['isOwner']): ?>
            <p>
                <a href="<?= $e($baseUrl) ?>editmovie.php?id=<?= (int) $movie->getId() ?>" class="btn btn-sm btn-light">
                    <i class="fas fa-edit"></i> Editar
                </a>
                <a href="<?= $e($baseUrl) ?>dashboard.php" class="btn btn-sm btn-outline-light">
                    <i class="fas fa-th-list"></i> Meus filmes
                </a>
            </p>
<?php endif; ?>
<?php if ($movie->getDescription() !== null && trim($movie->getDescription()) !== ''): ?>
            <h2 class="h5">Sinopse</h2>
            <p><?= nl2br($e($movie->getDescription())) ?></p>
<?php endif; ?>
        </div>
    </div>

<?php if ($trailerUrl !== null): ?>
    <section id="trailer" class="mb-4">
        <h2 class="section-title">Trailer</h2>
        <div class="embed-responsive embed-responsive-16by9">
            <iframe class="embed-responsive-item" src="<?= $e($trailerUrl) ?>"
                    title="Trailer de <?= $e($movie->getTitle()) ?>" allowfullscreen
                    allow="accelerometer; encrypted-media; gyroscope; picture-in-picture"
                    referrerpolicy="strict-origin-when-cross-origin"></iframe>
        </div>
    </section>
<?php endif; ?>

    <section id="reviews" class="mb-4">
        <h2 class="section-title">Avaliações</h2>
        <p id="reviews-average" class="movie-card-rating">Nota média: <span class="text-white-50">sem notas</span></p>
        <div id="reviews-list">
            <p class="text-white-50">Ainda não há avaliações.</p>
        </div>
    </section>
<?php endif; ?>
</div>

<?php
require_once __DIR__ . '/templates/footer.php';
