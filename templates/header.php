<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
/**
 * @var string $baseUrl
 * @var \MovieStar\Core\Session $session
 * @var \MovieStar\Models\User|null $currentUser
 * @var array{msg: string, type: string}|null $flash
 */
$e = static function (string $v): string {
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
};
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>MovieStar</title>
    <link rel="short icon" href="<?= $e($baseUrl) ?>img/moviestar.ico" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.6.2/css/bootstrap.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="<?= $e($baseUrl) ?>css/styles.css">
</head>
<body>
<header>
    <nav id="main-navbar" class="navbar navbar-expand-lg">
        <a href="<?= $e($baseUrl) ?>" class="navbar-brand">
            <img src="<?= $e($baseUrl) ?>img/logo.svg" alt="MovieStar" id="logo">
            <span id="moviestar-title">MovieStar</span>
        </a>

        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbar" aria-controls="navbar" aria-expanded="false" aria-label="Toggle navigation">
            <i class="fas fa-bars"></i>
        </button>

        <form action="" method="get" id="search-form" class="form-inline my-2 my-lg-0">
            <input type="text" name="q" id="search" class="form-control mr-sm-2" type="search" placeholder="Buscar Filmes" aria-label="Search">

            <button class="btn my-2 my-sm-0" type="submit">
                <i class="fas fa-search"></i>
            </button>
        </form>

        <div class="collapse navbar-collapse" id="navbar">
            <ul class="navbar-nav">
<?php if ($currentUser !== null): ?>
                <li class="nav-item">
                    <span class="nav-link"><?= $e($currentUser->getFullName()) ?></span>
                </li>
                <li class="nav-item">
                    <form action="<?= $e($baseUrl) ?>auth.php" method="post">
                        <input type="hidden" name="type" value="logout">
                        <input type="hidden" name="csrf_token" value="<?= $e($session->csrfToken()) ?>">
                        <button type="submit" class="btn btn-link nav-link">Sair</button>
                    </form>
                </li>
<?php else: ?>
                <li class="nav-item">
                    <a href="<?= $e($baseUrl) ?>auth.php" class="nav-link">Entrar / Cadastrar</a>
                </li>
<?php endif; ?>
            </ul>
        </div>
    </nav>
</header>

<?php if ($flash !== null): ?>
    <div class="msg-container">
        <p class="msg <?= $e($flash['type']) ?>"><?= $e($flash['msg']) ?></p>
    </div>
<?php endif; ?>

