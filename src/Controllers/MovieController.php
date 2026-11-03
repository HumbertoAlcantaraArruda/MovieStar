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
 * Processa o cadastro de filmes (exige usuário logado).
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
     * Exige login (em GET e POST) e trata o POST de criação, redirecionando ao final.
     */
    public function handle(): void
    {
        if ($this->currentUser === null) {
            $this->redirect('auth.php', 'Faça login para continuar.', 'error');
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        // Corpo maior que post_max_size: o PHP descarta $_POST e $_FILES inteiros.
        if (empty($_POST) && empty($_FILES) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            $this->redirect('newmovie.php', 'O envio excede o tamanho máximo permitido.', 'error');
        }

        if (!$this->session->isValidCsrfToken($_POST['csrf_token'] ?? null)) {
            $this->redirect('newmovie.php', 'Requisição inválida. Tente novamente.', 'error');
        }

        $this->create();
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

        $this->redirect('', 'Filme cadastrado com sucesso!');
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
