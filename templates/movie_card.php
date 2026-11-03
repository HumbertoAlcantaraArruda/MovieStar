<?php
declare(strict_types=1);

/**
 * Card de filme reutilizável (home, busca, perfil).
 *
 * @var \MovieStar\Models\Movie $movie    Filme a exibir
 * @var string                  $baseUrl
 * @var callable                $e        Escape de HTML (definido em header.php)
 * @var float|null              $rating   (opcional) nota média; só é exibida se informada
 */
$rating = $rating ?? null;
?>
<div class="col-6 col-md-4 col-lg-3 mb-4">
    <div class="card movie-card h-100">
        <img src="<?= $e($baseUrl) ?>img/movies/<?= $e($movie->getImageOrDefault()) ?>"
             class="card-img-top movie-card-img" alt="Capa de <?= $e($movie->getTitle()) ?>" loading="lazy">
        <div class="card-body d-flex flex-column">
<?php if ($rating !== null): ?>
            <p class="movie-card-rating mb-1">
                <i class="fas fa-star"></i> <?= $e(number_format((float) $rating, 1, ',', '')) ?>
            </p>
<?php endif; ?>
            <h5 class="card-title"><?= $e($movie->getTitle()) ?></h5>
            <p class="card-text text-muted small"><?= $e((string) $movie->getCategory()) ?></p>
            <a href="<?= $e($baseUrl) ?>movie.php?id=<?= (int) $movie->getId() ?>"
               class="btn btn-sm btn-outline-light mt-auto">Ver detalhes</a>
        </div>
    </div>
</div>
