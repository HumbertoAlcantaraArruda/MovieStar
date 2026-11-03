<?php
declare(strict_types=1);

use MovieStar\Core\Pagination;
use MovieStar\Models\Movie;

require_once __DIR__ . '/bootstrap.php';
/**
 * @var \MovieStar\DAO\MovieDAO $movieDAO
 * @var \MovieStar\DAO\ReviewDAO $reviewDAO
 * @var \MovieStar\Models\User|null $currentUser
 * @var string $baseUrl
 */

const HOME_LATEST_PER_PAGE = 8;
const HOME_CATEGORY_LIMIT = 4;

$pagination   = new Pagination($movieDAO->countAll(), HOME_LATEST_PER_PAGE);
$latestMovies = $movieDAO->findLatest(HOME_LATEST_PER_PAGE, $pagination->getOffset());

// Apenas categorias com filmes (na ordem fixa de Movie::CATEGORIES).
$moviesByCategory = [];
if (count($latestMovies) > 0) {
    $withMovies = $movieDAO->findCategoriesWithMovies();
    foreach (Movie::CATEGORIES as $category) {
        if (in_array($category, $withMovies, true)) {
            $moviesByCategory[$category] = $movieDAO->findByCategory($category, HOME_CATEGORY_LIMIT);
        }
    }
}

// Médias de todos os filmes exibidos em uma única consulta (id => média).
$movieIds = [];
foreach (array_merge($latestMovies, ...array_values($moviesByCategory)) as $movie) {
    $movieIds[] = (int) $movie->getId();
}
$ratings = $reviewDAO->getAverageRatings($movieIds);

require_once __DIR__ . '/templates/header.php';
?>
<div id="main-container" class="container-fluid">
<?php if (count($latestMovies) === 0): ?>
    <div class="text-center py-5">
        <i class="fas fa-film fa-3x mb-3 text-white-50"></i>
        <h2>Nenhum filme cadastrado ainda</h2>
<?php if ($currentUser !== null): ?>
        <p class="text-white-50">Seja o primeiro a adicionar um filme ao catálogo.</p>
        <a href="<?= $e($baseUrl) ?>newmovie.php" class="btn btn-light">
            <i class="fas fa-plus"></i> Cadastrar filme
        </a>
<?php else: ?>
        <p class="text-white-50">Entre ou crie uma conta para cadastrar o primeiro filme.</p>
        <a href="<?= $e($baseUrl) ?>auth.php" class="btn btn-light">Entrar / Cadastrar</a>
<?php endif; ?>
    </div>
<?php else: ?>
    <section id="latest-movies" class="mb-4">
        <h2 class="section-title">Filmes novos</h2>
        <div class="row">
<?php foreach ($latestMovies as $movie): ?>
<?php $rating = $ratings[(int) $movie->getId()] ?? null; ?>
<?php include __DIR__ . '/templates/movie_card.php'; ?>
<?php endforeach; ?>
        </div>
<?php include __DIR__ . '/templates/pagination.php'; ?>
    </section>

<?php foreach ($moviesByCategory as $category => $categoryMovies): ?>
    <section class="category-section mb-4">
        <h2 class="section-title"><?= $e((string) $category) ?></h2>
        <div class="row">
<?php foreach ($categoryMovies as $movie): ?>
<?php $rating = $ratings[(int) $movie->getId()] ?? null; ?>
<?php include __DIR__ . '/templates/movie_card.php'; ?>
<?php endforeach; ?>
        </div>
    </section>
<?php endforeach; ?>
<?php endif; ?>
</div>

<?php
require_once __DIR__ . '/templates/footer.php';
