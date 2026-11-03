<?php
declare(strict_types=1);

use MovieStar\Controllers\AuthController;
use MovieStar\Core\UserRules;

require_once __DIR__ . '/bootstrap.php';
/**
 * @var \MovieStar\DAO\UserDAO $userDAO
 * @var \MovieStar\Core\Session $session
 * @var \MovieStar\Core\Auth $auth
 * @var \MovieStar\Core\LoginThrottle $loginThrottle
 * @var string $baseUrl
 */

(new AuthController($userDAO, $session, $auth, $loginThrottle, $baseUrl))->handle();

require_once __DIR__ . '/templates/header.php';
?>
<?php
$oldLogin    = $session->pullOldInput('auth_login');
$oldRegister = $session->pullOldInput('auth_register');
?>
    <div id="main-container" class="container-fluid">
        <div class="row justify-content-center" id="auth-row">
            <!-- Entrar -->
            <div class="col-12 col-md-6 col-lg-4 mb-5" id="login-container">
                <h2>Entrar</h2>
                <form action="" method="post">
                    <input type="hidden" name="type" value="login">
                    <input type="hidden" name="csrf_token" value="<?= $e($session->csrfToken()) ?>">
                    <div class="form-group">
                        <label for="login-email">E-mail:</label>
                        <input type="email" class="form-control" id="login-email" name="email" required
                               maxlength="<?= AuthController::MAX_EMAIL_LENGTH ?>" autocomplete="email"
                               placeholder="Digite seu e-mail" value="<?= $e($oldLogin['email'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="login-password">Senha:</label>
                        <input type="password" class="form-control" id="login-password" name="password" required
                               maxlength="<?= UserRules::MAX_PASSWORD_LENGTH ?>" autocomplete="current-password"
                               placeholder="Digite sua senha">
                    </div>
                    <button type="submit" class="btn card-btn btn-block">Entrar</button>
                </form>
            </div>
            <!-- Criar conta -->
            <div class="col-12 col-md-6 col-lg-4 mb-5" id="register-container">
                <h2>Criar Conta</h2>
                <form action="" method="post">
                    <input type="hidden" name="type" value="register">
                    <input type="hidden" name="csrf_token" value="<?= $e($session->csrfToken()) ?>">
                    <div class="form-group">
                        <label for="register-email">E-mail:</label>
                        <input type="email" class="form-control" id="register-email" name="email" required
                               maxlength="<?= AuthController::MAX_EMAIL_LENGTH ?>" autocomplete="email"
                               placeholder="Digite seu e-mail" value="<?= $e($oldRegister['email'] ?? '') ?>">
                    </div>
                    <div class="form-row">
                        <div class="form-group col-sm-6">
                            <label for="register-name">Nome:</label>
                            <input type="text" class="form-control" id="register-name" name="name" required
                                   maxlength="<?= UserRules::MAX_FIELD_LENGTH ?>" autocomplete="given-name"
                                   placeholder="Digite seu nome" value="<?= $e($oldRegister['name'] ?? '') ?>">
                        </div>
                        <div class="form-group col-sm-6">
                            <label for="register-lastname">Sobrenome:</label>
                            <input type="text" class="form-control" id="register-lastname" name="lastname" required
                                   maxlength="<?= UserRules::MAX_FIELD_LENGTH ?>" autocomplete="family-name"
                                   placeholder="Digite seu sobrenome" value="<?= $e($oldRegister['lastname'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="register-password">Senha:</label>
                        <input type="password" class="form-control" id="register-password" name="password" required
                               minlength="<?= UserRules::MIN_PASSWORD_LENGTH ?>" maxlength="<?= UserRules::MAX_PASSWORD_LENGTH ?>"
                               autocomplete="new-password" placeholder="Digite sua senha">
                        <small class="form-text text-white-50">De <?= UserRules::MIN_PASSWORD_LENGTH ?> a <?= UserRules::MAX_PASSWORD_LENGTH ?> caracteres.</small>
                    </div>
                    <div class="form-group">
                        <label for="register-confirmpassword">Confirmação de Senha:</label>
                        <input type="password" class="form-control" id="register-confirmpassword" name="confirmpassword" required
                               maxlength="<?= UserRules::MAX_PASSWORD_LENGTH ?>" autocomplete="new-password"
                               placeholder="Confirme sua senha">
                    </div>
                    <button type="submit" class="btn card-btn btn-block">Registrar</button>
                </form>
            </div>
        </div>
    </div>

<?php
require_once __DIR__ . '/templates/footer.php';
