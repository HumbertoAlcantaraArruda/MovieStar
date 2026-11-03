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

// Exige login e posse do filme (redireciona ao painel se não for o dono); trata o POST de edição.
$movie = (new MovieController($movieDAO, $imageUploader, $session, $auth, $baseUrl))->handleEdit();

require_once __DIR__ . '/templates/header.php';
?>
    <div id="main-container" class="container-fluid">
        <div class="row justify-content-center">
            <div class="col-12 col-md-8 col-lg-6">
                <h1>Editar filme</h1>
                <p class="text-white-50">Atualize os dados de "<?= $e($movie->getTitle()) ?>".</p>

<?php
$submitLabel = 'Salvar alterações';
require __DIR__ . '/templates/movie_form.php';
?>
            </div>
        </div>
    </div>

<?php
require_once __DIR__ . '/templates/footer.php';
