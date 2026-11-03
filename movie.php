<?php
declare(strict_types=1);

use MovieStar\Controllers\MoviePageController;
use MovieStar\Controllers\ReviewController;
use MovieStar\Models\Review;

require_once __DIR__ . '/bootstrap.php';
/**
 * @var \MovieStar\DAO\MovieDAO $movieDAO
 * @var \MovieStar\DAO\UserDAO $userDAO
 * @var \MovieStar\DAO\ReviewDAO $reviewDAO
 * @var \MovieStar\Core\Session $session
 * @var \MovieStar\Core\Auth $auth
 * @var \MovieStar\Models\User|null $currentUser
 * @var string $baseUrl
 */

// POST: grava a avaliação e redireciona (nada é exibido daqui para baixo).
(new ReviewController($movieDAO, $reviewDAO, $session, $auth, $baseUrl))->handle();

// Responde 404 (sem redirecionar) quando o id é inválido ou o filme não existe.
$page = (new MoviePageController($movieDAO, $userDAO, $reviewDAO, $auth))->handleShow();

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

<?php
    $reviews = $page['reviews'];
    $reviewCount = count($reviews);
?>
    <section id="reviews" class="mb-4">
        <h2 class="section-title">Avaliações</h2>
<?php if ($page['averageRating'] !== null): ?>
        <p id="reviews-average" class="movie-card-rating">
            <i class="fas fa-star"></i> Nota média: <?= $e(number_format($page['averageRating'], 1, ',', '')) ?>
            <span class="text-white-50 font-weight-normal">
                (<?= $reviewCount ?> <?= $reviewCount === 1 ? 'avaliação' : 'avaliações' ?>)
            </span>
        </p>
<?php else: ?>
        <p id="reviews-average" class="movie-card-rating">Nota média: <span class="text-white-50">sem notas</span></p>
<?php endif; ?>

        <div id="review-form-container" class="mb-4">
<?php if ($page['canReview']): ?>
            <form action="<?= $e($baseUrl) ?>movie.php?id=<?= (int) $movie->getId() ?>" method="post" class="review-form">
                <input type="hidden" name="csrf_token" value="<?= $e($session->csrfToken()) ?>">
                <h3 class="h5">Avalie este filme</h3>
                <div class="form-row">
                    <div class="form-group col-12 col-sm-4 col-md-3">
                        <label for="rating">Nota</label>
                        <select name="rating" id="rating" class="form-control" required>
                            <option value="">Selecione</option>
<?php for ($n = Review::MAX_RATING; $n >= Review::MIN_RATING; $n--): ?>
                            <option value="<?= $n ?>"><?= $n ?></option>
<?php endfor; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label for="review">Comentário <span class="text-white-50 small">(opcional)</span></label>
                    <textarea name="review" id="review" class="form-control" rows="4"
                              maxlength="<?= ReviewController::MAX_REVIEW_LENGTH ?>"
                              placeholder="O que você achou do filme?"></textarea>
                    <small class="form-text text-white-50">Até <?= ReviewController::MAX_REVIEW_LENGTH ?> caracteres.</small>
                </div>
                <button type="submit" class="btn btn-light">
                    <i class="fas fa-paper-plane"></i> Enviar avaliação
                </button>
            </form>
<?php elseif ($currentUser === null): ?>
            <p class="text-white-50">
                <a href="<?= $e($baseUrl) ?>auth.php">Entre para avaliar</a> este filme.
            </p>
<?php elseif ($page['hasReviewed']): ?>
            <p class="text-white-50"><i class="fas fa-check"></i> Você já avaliou este filme.</p>
<?php elseif ($page['isOwner']): ?>
            <p class="text-white-50 small">Você cadastrou este filme, por isso não pode avaliá-lo.</p>
<?php endif; ?>
        </div>

        <div id="reviews-list">
<?php if ($reviewCount === 0): ?>
            <p class="text-white-50">Ainda não há avaliações.</p>
<?php else: ?>
<?php foreach ($reviews as $review): ?>
            <div class="card review-card mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between flex-wrap">
                        <h3 class="h6 mb-1"><?= $e((string) $review->getAuthorName()) ?></h3>
                        <small class="text-white-50"><?= $e($review->getCreatedAtFormatted()) ?></small>
                    </div>
                    <p class="movie-card-rating mb-2">
                        <i class="fas fa-star"></i> <?= (int) $review->getRating() ?>/<?= Review::MAX_RATING ?>
                    </p>
<?php if ($review->getReview() !== null): ?>
                    <p class="card-text mb-0"><?= nl2br($e($review->getReview())) ?></p>
<?php endif; ?>
                </div>
            </div>
<?php endforeach; ?>
<?php endif; ?>
        </div>
    </section>
<?php endif; ?>
</div>

<?php
require_once __DIR__ . '/templates/footer.php';
