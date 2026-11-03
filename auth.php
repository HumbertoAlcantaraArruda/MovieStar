<?php
declare(strict_types=1);

use MovieStar\Controllers\AuthController;

require_once __DIR__ . '/bootstrap.php';
/**
 * @var \MovieStar\DAO\UserDAO $userDAO
 * @var \MovieStar\Core\Session $session
 * @var \MovieStar\Core\Auth $auth
 * @var string $baseUrl
 */

(new AuthController($userDAO, $session, $auth, $baseUrl))->handle();

require_once __DIR__ . '/templates/header.php';
?>
    <div id="main-container" class="container-fluid">
        <div class="col-md-12">
            <div class="row" id="auth-row">
                <!-- Entrar -->
                <div class="col-md-4" id="login-container">
                    <h2>Entrar</h2>
                    <form action="" method="post">
                        <input type="hidden" name="type" value="login">
                        <input type="hidden" name="csrf_token" value="<?= $e($session->csrfToken()) ?>">
                        <div class="form-group">
                            <label for="email">E-mail:</label>
                            <input type="email" class="form-control" id="email" name="email" placeholder="Digite seu e-mail">
                        </div>
                        <div class="form-group">
                            <label for="password">Senha:</label>
                            <input type="password" class="form-control" id="password" name="password" placeholder="Digite sua senha">
                        </div>
                        <input type="submit" class="btn card-btn" value="entrar">
                    </form>
                </div>
                <!-- Criar conta -->
                <div class="col-md-4" id="register-container">
                    <h2>Criar Conta</h2>
                    <form action="" method="post">
                        <input type="hidden" name="type" value="register">
                        <input type="hidden" name="csrf_token" value="<?= $e($session->csrfToken()) ?>">
                        <div class="form-group">
                            <label for="email">E-mail:</label>
                            <input type="email" class="form-control" id="email" name="email" placeholder="Digite seu e-mail">
                        </div>
                        <div class="form-group">
                            <label for="name">Name:</label>
                            <input type="text" class="form-control" id="name" name="name" placeholder="Digite seu nome">
                        </div>
                        <div class="form-group">
                            <label for="lastname">Sobrenome:</label>
                            <input type="text" class="form-control" id="lastname" name="lastname" placeholder="Digite seu sobrenome">
                        </div>
                        <div class="form-group">
                            <label for="password">Senha:</label>
                            <input type="password" class="form-control" id="password" name="password" placeholder="Digite sua senha">
                        </div>
                        <div class="form-group">
                            <label for="confirmpassword">Confirmação de Senha:</label>
                            <input type="password" class="form-control" id="confirmpassword" name="confirmpassword" placeholder="Confirme sua senha">
                        </div>
                        <input type="submit" class="btn card-btn" value="Registrar">
                    </form>
                </div>
            </div>
        </div>
    </div>

<?php
require_once __DIR__ . '/templates/footer.php';
