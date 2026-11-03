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

                <form action="" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= $e($session->csrfToken()) ?>">

                    <div class="form-group">
                        <label for="title">Título:</label>
                        <input type="text" class="form-control" id="title" name="title" maxlength="100" required
                               placeholder="Digite o título do filme">
                    </div>

                    <div class="form-group">
                        <label for="image">Imagem (opcional):</label>
                        <input type="file" class="form-control-file" id="image" name="image"
                               accept="image/jpeg,image/png,image/webp">
                        <small class="form-text text-white-50">JPG, PNG ou WEBP, até 2 MB.</small>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="category">Categoria:</label>
                            <select class="form-control" id="category" name="category" required>
                                <option value="">Selecione</option>
<?php foreach (Movie::CATEGORIES as $category): ?>
                                <option value="<?= $e($category) ?>"><?= $e($category) ?></option>
<?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="length">Duração:</label>
                            <input type="text" class="form-control" id="length" name="length" maxlength="50"
                                   placeholder="Ex.: 2h 10min">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="trailer">Trailer (opcional):</label>
                        <input type="url" class="form-control" id="trailer" name="trailer" maxlength="255"
                               placeholder="https://www.youtube.com/watch?v=...">
                        <small class="form-text text-white-50">Link do YouTube (youtube.com/watch, youtu.be ou youtube.com/embed).</small>
                    </div>

                    <div class="form-group">
                        <label for="description">Descrição:</label>
                        <textarea class="form-control" id="description" name="description" rows="5" maxlength="5000" required
                                  placeholder="Descreva o filme"></textarea>
                    </div>

                    <input type="submit" class="btn btn-light card-btn" value="Cadastrar filme">
                </form>
            </div>
        </div>
    </div>

<?php
require_once __DIR__ . '/templates/footer.php';
