<?php
declare(strict_types=1);

use MovieStar\Controllers\MovieController;
use MovieStar\Core\ImageUploader;
use MovieStar\Models\Movie;

require_once __DIR__ . '/bootstrap.php';
/**
 * @var \MovieStar\DAO\MovieDAO $movieDAO
 * @var \MovieStar\Core\Session $session
 * @var \MovieStar\Models\User|null $currentUser
 * @var string $baseUrl
 */

$imageUploader = new ImageUploader(__DIR__ . '/img/movies');

// Redireciona para auth.php se não houver login; trata o POST de cadastro.
(new MovieController($movieDAO, $imageUploader, $session, $currentUser, $baseUrl))->handle();

require_once __DIR__ . '/templates/header.php';
?>
    <div id="main-container" class="container-fluid">
        <div class="row justify-content-center">
            <div class="col-12 col-md-8 col-lg-6">
                <h1>Incluir filme</h1>
                <p class="text-white-50">Cadastre um filme e compartilhe com a comunidade.</p>

<?php
$movie       = null;
$submitLabel = 'Cadastrar filme';
require __DIR__ . '/templates/movie_form.php';
?>
            </div>
        </div>
    </div>

<?php
require_once __DIR__ . '/templates/footer.php';
