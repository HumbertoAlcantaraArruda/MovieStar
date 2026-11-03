<?php
declare(strict_types=1);

use MovieStar\Controllers\ProfileController;
use MovieStar\Core\ImageUploader;
use MovieStar\Core\UserRules;

require_once __DIR__ . '/bootstrap.php';
/**
 * @var \MovieStar\DAO\UserDAO $userDAO
 * @var \MovieStar\DAO\MovieDAO $movieDAO
 * @var \MovieStar\DAO\ReviewDAO $reviewDAO
 * @var \MovieStar\Core\Session $session
 * @var \MovieStar\Core\Auth $auth
 * @var string $baseUrl
 */

$imageUploader = new ImageUploader(__DIR__ . '/img/users');

// Exige login; trata o POST de dados ou de senha.
$user = (new ProfileController($userDAO, $movieDAO, $reviewDAO, $imageUploader, $session, $auth, $baseUrl))->handleEdit();

require_once __DIR__ . '/templates/header.php';
?>
    <div id="main-container" class="container-fluid">
        <div class="row justify-content-center">
            <div class="col-12 col-md-8 col-lg-6">
                <h1>Editar perfil</h1>

                <form action="" method="post" enctype="multipart/form-data" class="mb-5">
                    <input type="hidden" name="type" value="profile">
                    <input type="hidden" name="csrf_token" value="<?= $e($session->csrfToken()) ?>">

                    <div class="form-group">
                        <label for="image">Foto (opcional):</label>
                        <div class="mb-2">
                            <img src="<?= $e($baseUrl) ?>img/users/<?= $e($user->getImageOrDefault()) ?>" alt="Foto atual"
                                 class="img-thumbnail" style="max-height: 150px;">
                        </div>
                        <input type="file" class="form-control-file" id="image" name="image"
                               accept="image/jpeg,image/png,image/webp">
                        <small class="form-text text-white-50">JPG, PNG ou WEBP, até 2 MB. Deixe em branco para manter a foto atual.</small>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="name">Nome:</label>
                            <input type="text" class="form-control" id="name" name="name" required
                                   maxlength="<?= UserRules::MAX_FIELD_LENGTH ?>" value="<?= $e($user->getName()) ?>">
                        </div>
                        <div class="form-group col-md-6">
                            <label for="lastname">Sobrenome:</label>
                            <input type="text" class="form-control" id="lastname" name="lastname" required
                                   maxlength="<?= UserRules::MAX_FIELD_LENGTH ?>" value="<?= $e($user->getLastname()) ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="email">E-mail:</label>
                        <input type="email" class="form-control" id="email" value="<?= $e($user->getEmail()) ?>" readonly>
                    </div>

                    <div class="form-group">
                        <label for="bio">Bio (opcional):</label>
                        <textarea class="form-control" id="bio" name="bio" rows="5"
                                  maxlength="<?= UserRules::MAX_BIO_LENGTH ?>"
                                  placeholder="Conte um pouco sobre você"><?= $e((string) $user->getBio()) ?></textarea>
                        <small class="form-text text-white-50">Até <?= UserRules::MAX_BIO_LENGTH ?> caracteres.</small>
                    </div>

                    <button type="submit" class="btn btn-light card-btn">Salvar alterações</button>
                    <a href="<?= $e($baseUrl) ?>profile.php?id=<?= (int) $user->getId() ?>" class="btn btn-outline-light">Ver perfil</a>
                </form>

                <h2 class="h4">Alterar senha</h2>
                <form action="" method="post">
                    <input type="hidden" name="type" value="password">
                    <input type="hidden" name="csrf_token" value="<?= $e($session->csrfToken()) ?>">

                    <div class="form-group">
                        <label for="current_password">Senha atual:</label>
                        <input type="password" class="form-control" id="current_password" name="current_password"
                               required autocomplete="current-password">
                    </div>
                    <div class="form-group">
                        <label for="password">Nova senha:</label>
                        <input type="password" class="form-control" id="password" name="password" required
                               minlength="<?= UserRules::MIN_PASSWORD_LENGTH ?>" autocomplete="new-password">
                        <small class="form-text text-white-50">Mínimo de <?= UserRules::MIN_PASSWORD_LENGTH ?> caracteres.</small>
                    </div>
                    <div class="form-group">
                        <label for="confirmpassword">Confirme a nova senha:</label>
                        <input type="password" class="form-control" id="confirmpassword" name="confirmpassword"
                               required autocomplete="new-password">
                    </div>

                    <button type="submit" class="btn btn-light card-btn">Alterar senha</button>
                </form>
            </div>
        </div>
    </div>

<?php
require_once __DIR__ . '/templates/footer.php';
