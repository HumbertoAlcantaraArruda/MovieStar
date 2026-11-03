<?php

declare(strict_types=1);

namespace MovieStar\Controllers;

use MovieStar\Core\ImageUploader;
use MovieStar\Core\Session;
use MovieStar\Core\UploadException;
use MovieStar\Core\YouTube;
use MovieStar\DAO\MovieDAO;
use MovieStar\Models\Movie;
use MovieStar\Models\User;
use PDOException;

/**
 * Processa cadastro, edição e exclusão de filmes (exige usuário logado; só o dono altera).
 */
class MovieController
{
    private const MAX_TITLE_LENGTH       = 100;
    private const MAX_DESCRIPTION_LENGTH = 5000;
    private const MAX_LENGTH_LENGTH      = 50;

    private MovieDAO $movieDAO;
    private ImageUploader $imageUploader;
    private Session $session;
    private ?User $currentUser;
    private string $baseUrl;

    public function __construct(
        MovieDAO $movieDAO,
        ImageUploader $imageUploader,
        Session $session,
        ?User $currentUser,
        string $baseUrl
    ) {
        $this->movieDAO      = $movieDAO;
        $this->imageUploader = $imageUploader;
        $this->session       = $session;
        $this->currentUser   = $currentUser;
        $this->baseUrl       = $baseUrl;
    }

    /**
     * Página de cadastro: exige login e trata o POST de criação, redirecionando ao final.
     */
    public function handle(): void
    {
        $this->requireLogin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $this->guardPost('newmovie.php');
        $this->create();
    }

    /**
     * Página de edição: exige login e um filme existente do usuário (GET e POST).
     * Trata o POST de atualização e, em GET, devolve o filme para preencher o formulário.
     */
    public function handleEdit(): Movie
    {
        $this->requireLogin();

        $movie     = $this->loadOwnedMovie();
        $errorPath = 'editmovie.php?id=' . (int) $movie->getId();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->guardPost($errorPath);
            $this->update($movie, $errorPath);
        }

        return $movie;
    }

    /**
     * Painel do usuário: exige login e trata o POST de exclusão.
     */
    public function handleDashboard(): void
    {
        $this->requireLogin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $this->guardPost('dashboard.php');
        $this->delete();
    }

    private function requireLogin(): void
    {
        if ($this->currentUser === null) {
            $this->redirect('auth.php', 'Faça login para continuar.', 'error');
        }
    }

    /**
     * Valida tamanho do corpo e token CSRF de um POST.
     */
    private function guardPost(string $errorPath): void
    {
        // Corpo maior que post_max_size: o PHP descarta $_POST e $_FILES inteiros.
        if (empty($_POST) && empty($_FILES) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            $this->redirect($errorPath, 'O envio excede o tamanho máximo permitido.', 'error');
        }

        if (!$this->session->isValidCsrfToken($_POST['csrf_token'] ?? null)) {
            $this->redirect($errorPath, 'Requisição inválida. Tente novamente.', 'error');
        }
    }

    /**
     * Carrega o filme do parâmetro "id" (GET ou POST) garantindo que pertence ao usuário logado.
     * Inexistente e de outro usuário recebem a mesma resposta, sem revelar a diferença.
     */
    private function loadOwnedMovie(): Movie
    {
        $raw = $_GET['id'] ?? $_POST['id'] ?? '';
        $id  = is_string($raw) && ctype_digit($raw) && strlen($raw) <= 10 ? (int) $raw : 0;

        $movie = $id > 0 ? $this->movieDAO->findById($id) : null;

        if ($movie === null || $movie->getUserId() !== (int) $this->currentUser->getId()) {
            $this->redirect('dashboard.php', 'Filme não encontrado.', 'error');
        }

        return $movie;
    }

    private function create(): void
    {
        $movie = $this->buildMovieFromRequest('newmovie.php');
        $movie->setUserId((int) $this->currentUser->getId());

        // Imagem é opcional: sem capa, as páginas usam Movie::DEFAULT_IMAGE.
        $file = isset($_FILES['image']) && is_array($_FILES['image']) ? $_FILES['image'] : null;

        if ($this->imageUploader->wasSent($file)) {
            try {
                $movie->setImage($this->imageUploader->upload($file));
            } catch (UploadException $e) {
                $this->redirect('newmovie.php', $e->getMessage(), 'error');
            }
        }

        try {
            $this->movieDAO->create($movie);
        } catch (PDOException $e) {
            $this->imageUploader->delete($movie->getImage());
            error_log('Falha ao cadastrar filme: ' . $e->getMessage());
            $this->redirect('newmovie.php', 'Não foi possível cadastrar o filme. Tente novamente.', 'error');
        }

        // Leva ao painel, onde o usuário vê o filme recém-criado e pode editá-lo.
        $this->redirect('dashboard.php', 'Filme cadastrado com sucesso!');
    }

    private function update(Movie $existing, string $errorPath): void
    {
        $data     = $this->buildMovieFromRequest($errorPath);
        $oldImage = $existing->getImage();
        $newImage = null;

        $file = isset($_FILES['image']) && is_array($_FILES['image']) ? $_FILES['image'] : null;

        if ($this->imageUploader->wasSent($file)) {
            try {
                $newImage = $this->imageUploader->upload($file);
            } catch (UploadException $e) {
                $this->redirect($errorPath, $e->getMessage(), 'error');
            }
        }

        // Dono e id vêm do registro já validado, nunca do formulário.
        $existing
            ->setTitle($data->getTitle())
            ->setDescription($data->getDescription())
            ->setCategory($data->getCategory())
            ->setLength($data->getLength())
            ->setTrailer($data->getTrailer());

        if ($newImage !== null) {
            $existing->setImage($newImage);
        }

        try {
            $this->movieDAO->update($existing);
        } catch (PDOException $e) {
            $this->imageUploader->delete($newImage);
            error_log('Falha ao atualizar filme: ' . $e->getMessage());
            $this->redirect($errorPath, 'Não foi possível atualizar o filme. Tente novamente.', 'error');
        }

        // Só apaga a capa antiga depois que o UPDATE deu certo.
        if ($newImage !== null) {
            $this->imageUploader->delete($oldImage);
        }

        $this->redirect('dashboard.php', 'Filme atualizado com sucesso!');
    }

    private function delete(): void
    {
        $movie = $this->loadOwnedMovie();

        try {
            $this->movieDAO->delete((int) $movie->getId());
        } catch (PDOException $e) {
            error_log('Falha ao excluir filme: ' . $e->getMessage());
            $this->redirect('dashboard.php', 'Não foi possível excluir o filme. Tente novamente.', 'error');
        }

        $this->imageUploader->delete($movie->getImage());
        $this->redirect('dashboard.php', 'Filme excluído com sucesso!');
    }

    /**
     * Lê e valida os campos de texto do formulário (sem imagem nem dono).
     * Em caso de erro, redireciona para $errorPath com a mensagem.
     */
    private function buildMovieFromRequest(string $errorPath): Movie
    {
        $title       = $this->postString('title');
        $description = $this->postString('description');
        $category    = $this->postString('category');
        $length      = $this->postString('length');
        $trailer     = $this->postString('trailer');

        if ($title === '' || $description === '' || $category === '') {
            $this->redirect($errorPath, 'Preencha título, descrição e categoria.', 'error');
        }

        if (mb_strlen($title) > self::MAX_TITLE_LENGTH) {
            $this->redirect($errorPath, 'O título deve ter no máximo ' . self::MAX_TITLE_LENGTH . ' caracteres.', 'error');
        }

        if (mb_strlen($description) > self::MAX_DESCRIPTION_LENGTH) {
            $this->redirect($errorPath, 'A descrição deve ter no máximo ' . self::MAX_DESCRIPTION_LENGTH . ' caracteres.', 'error');
        }

        if (!Movie::isValidCategory($category)) {
            $this->redirect($errorPath, 'Selecione uma categoria válida.', 'error');
        }

        if (mb_strlen($length) > self::MAX_LENGTH_LENGTH) {
            $this->redirect($errorPath, 'A duração deve ter no máximo ' . self::MAX_LENGTH_LENGTH . ' caracteres.', 'error');
        }

        $trailerUrl = null;

        if ($trailer !== '') {
            $videoId = YouTube::extractVideoId($trailer);

            if ($videoId === null) {
                $this->redirect($errorPath, 'Informe um link válido do YouTube para o trailer.', 'error');
            }

            $trailerUrl = YouTube::embedUrl($videoId);
        }

        return (new Movie())
            ->setTitle($title)
            ->setDescription($description)
            ->setCategory($category)
            ->setLength($length !== '' ? $length : null)
            ->setTrailer($trailerUrl);
    }

    /**
     * Valor de $_POST como string aparada (arrays, ausentes e UTF-8 inválido viram '').
     */
    private function postString(string $key): string
    {
        $value = $_POST[$key] ?? '';

        return is_string($value) && mb_check_encoding($value, 'UTF-8') ? trim($value) : '';
    }

    /**
     * @return never
     */
    private function redirect(string $path, string $message, string $type = 'success'): void
    {
        $this->session->setFlash($message, $type);
        header('Location: ' . $this->baseUrl . $path);
        exit;
    }
}
