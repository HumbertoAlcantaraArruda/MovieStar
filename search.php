<?php
declare(strict_types=1);

use MovieStar\Core\Pagination;

require_once __DIR__ . '/bootstrap.php';
/**
 * @var \MovieStar\DAO\MovieDAO $movieDAO
 * @var \MovieStar\DAO\ReviewDAO $reviewDAO
 * @var string $baseUrl
 */

const SEARCH_PER_PAGE = 12;
const SEARCH_MAX_LENGTH = 100;

$rawTerm = $_GET['q'] ?? '';
// Arrays (q[]=) e UTF-8 inválido viram busca vazia.
$searchTerm = is_string($rawTerm) && mb_check_encoding($rawTerm, 'UTF-8') ? trim($rawTerm) : '';
if (mb_strlen($searchTerm, 'UTF-8') > SEARCH_MAX_LENGTH) {
    $searchTerm = trim(mb_substr($searchTerm, 0, SEARCH_MAX_LENGTH, 'UTF-8'));
}

$results = [];
$ratings = [];
if ($searchTerm !== '') {
    $pagination = new Pagination($movieDAO->countSearch($searchTerm), SEARCH_PER_PAGE);
    $results = $movieDAO->search($searchTerm, SEARCH_PER_PAGE, $pagination->getOffset());
    $movieIds = [];
    foreach ($results as $movie) {
        $movieIds[] = (int) $movie->getId();
    }
    $ratings = $reviewDAO->getAverageRatings($movieIds);
}

require_once __DIR__ . '/templates/header.php';
?>
<div id="main-container" class="container-fluid">
<?php if ($searchTerm === ''): ?>
    <div class="text-center py-5">
        <i class="fas fa-search fa-3x mb-3 text-white-50"></i>
        <h2>Busque um filme</h2>
        <p class="text-white-50">Digite um termo no campo de busca para encontrar filmes.</p>
    </div>
<?php elseif (count($results) === 0): ?>
    <div class="text-center py-5">
        <i class="fas fa-film fa-3x mb-3 text-white-50"></i>
        <h2>Nenhum filme encontrado</h2>
        <p class="text-white-50">Nada encontrado para &ldquo;<?= $e($searchTerm) ?>&rdquo;.</p>
        <a href="<?= $e($baseUrl) ?>" class="btn btn-light">Voltar para a home</a>
    </div>
<?php else: ?>
    <section id="search-results" class="mb-4">
        <h2 class="section-title">Resultados para &ldquo;<?= $e($searchTerm) ?>&rdquo;</h2>
        <div class="row">
<?php foreach ($results as $movie): ?>
<?php $rating = $ratings[(int) $movie->getId()] ?? null; ?>
<?php include __DIR__ . '/templates/movie_card.php'; ?>
<?php endforeach; ?>
        </div>
<?php $paginationParams = ['q' => $searchTerm]; include __DIR__ . '/templates/pagination.php'; ?>
    </section>
<?php endif; ?>
</div>

<?php
require_once __DIR__ . '/templates/footer.php';
