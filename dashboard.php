<?php
declare(strict_types=1);

use MovieStar\Controllers\MovieController;
use MovieStar\Core\ImageUploader;

require_once __DIR__ . '/bootstrap.php';
/**
 * @var \MovieStar\DAO\MovieDAO $movieDAO
 * @var \MovieStar\Core\Session $session
 * @var \MovieStar\Core\Auth $auth
 * @var string $baseUrl
 */

$imageUploader = new ImageUploader(__DIR__ . '/img/movies');

// Exige login; trata o POST de exclusão.
(new MovieController($movieDAO, $imageUploader, $session, $auth, $baseUrl))->handleDashboard();

$movies = $movieDAO->findByUser((int) $auth->requireLogin()->getId());

require_once __DIR__ . '/templates/header.php';
?>
    <div id="main-container" class="container-fluid">
        <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
            <h1 class="mb-0">Meus filmes</h1>
            <a href="<?= $e($baseUrl) ?>newmovie.php" class="btn btn-light card-btn">
                <i class="fas fa-plus"></i> Incluir filme
            </a>
        </div>

<?php if (count($movies) === 0): ?>
        <p class="text-white-50">
            Você ainda não cadastrou nenhum filme.
            <a href="<?= $e($baseUrl) ?>newmovie.php">Cadastre o primeiro</a>.
        </p>
<?php else: ?>
        <div class="table-responsive">
            <table class="table table-dark table-hover align-middle">
                <thead>
                    <tr>
                        <th scope="col">Capa</th>
                        <th scope="col">Título</th>
                        <th scope="col">Categoria</th>
                        <th scope="col" class="text-right">Ações</th>
                    </tr>
                </thead>
                <tbody>
<?php foreach ($movies as $movie): ?>
                    <tr>
                        <td>
                            <img src="<?= $e($baseUrl) ?>img/movies/<?= $e($movie->getImageOrDefault()) ?>"
                                 alt="Capa de <?= $e($movie->getTitle()) ?>" style="width: 60px; height: 80px; object-fit: cover;">
                        </td>
                        <td><?= $e($movie->getTitle()) ?></td>
                        <td><?= $e((string) $movie->getCategory()) ?></td>
                        <td class="text-right text-nowrap">
                            <a href="<?= $e($baseUrl) ?>editmovie.php?id=<?= (int) $movie->getId() ?>"
                               class="btn btn-sm btn-outline-light">
                                <i class="fas fa-pen"></i> Editar
                            </a>
                            <form action="" method="post" class="d-inline"
                                  onsubmit="return confirm('Excluir este filme? Esta ação não pode ser desfeita.');">
                                <input type="hidden" name="csrf_token" value="<?= $e($session->csrfToken()) ?>">
                                <input type="hidden" name="id" value="<?= (int) $movie->getId() ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                    <i class="fas fa-trash"></i> Excluir
                                </button>
                            </form>
                        </td>
                    </tr>
<?php endforeach; ?>
                </tbody>
            </table>
        </div>
<?php endif; ?>
    </div>

<?php
require_once __DIR__ . '/templates/footer.php';
