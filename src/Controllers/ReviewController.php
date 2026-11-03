<?php

declare(strict_types=1);

namespace MovieStar\Controllers;

use MovieStar\Core\Auth;
use MovieStar\Core\Session;
use MovieStar\DAO\DuplicateReviewException;
use MovieStar\DAO\MovieDAO;
use MovieStar\DAO\ReviewDAO;
use MovieStar\Models\Review;
use PDOException;

/**
 * Processa o envio de avaliação na página do filme (POST em movie.php?id=).
 * Exige login; o dono do filme não avalia e cada usuário avalia uma única vez.
 */
class ReviewController
{
    public const MAX_REVIEW_LENGTH = 2000;

    private const MSG_ALREADY_REVIEWED = 'Você já avaliou este filme.';

    private MovieDAO $movieDAO;
    private ReviewDAO $reviewDAO;
    private Session $session;
    private Auth $auth;
    private string $baseUrl;

    public function __construct(
        MovieDAO $movieDAO,
        ReviewDAO $reviewDAO,
        Session $session,
        Auth $auth,
        string $baseUrl
    ) {
        $this->movieDAO  = $movieDAO;
        $this->reviewDAO = $reviewDAO;
        $this->session   = $session;
        $this->auth      = $auth;
        $this->baseUrl   = $baseUrl;
    }

    /**
     * Em POST, valida e grava a avaliação e sempre redireciona; em GET não faz nada.
     */
    public function handle(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $user = $this->auth->requireLogin();

        // O id vem da query string (mesma fonte usada para exibir a página).
        $movieId   = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $movie     = is_int($movieId) ? $this->movieDAO->findById($movieId) : null;
        $moviePath = 'movie.php?id=' . (is_int($movieId) ? $movieId : 0);

        $token = $_POST['csrf_token'] ?? null;
        if (!is_string($token) || !$this->session->isValidCsrfToken($token)) {
            $this->redirect($movie !== null ? $moviePath : '', 'Requisição inválida. Tente novamente.', 'error');
        }

        if ($movie === null) {
            $this->redirect('', 'Filme não encontrado.', 'error');
        }

        if ($this->auth->owns($movie->getUserId())) {
            $this->redirect($moviePath, 'Você não pode avaliar um filme que cadastrou.', 'error');
        }

        $rating = $this->postRating();

        if ($rating === null) {
            $this->redirect($moviePath, 'Escolha uma nota de ' . Review::MIN_RATING . ' a ' . Review::MAX_RATING . '.', 'error');
        }

        $comment = $this->postComment($moviePath);

        $userId = (int) $user->getId();

        if ($this->reviewDAO->hasUserReviewed($userId, (int) $movie->getId())) {
            $this->redirect($moviePath, self::MSG_ALREADY_REVIEWED, 'error');
        }

        $review = (new Review())
            ->setRating($rating)
            ->setReview($comment)
            ->setUserId($userId)
            ->setMovieId((int) $movie->getId());

        // Um único INSERT: já é atômico, não precisa de transação.
        // A UNIQUE (users_id, movies_id) cobre a corrida entre a checagem acima e o INSERT.
        try {
            $this->reviewDAO->create($review);
        } catch (DuplicateReviewException $e) {
            $this->redirect($moviePath, self::MSG_ALREADY_REVIEWED, 'error');
        } catch (PDOException $e) {
            error_log('Falha ao gravar avaliação: ' . $e->getMessage());
            $this->redirect($moviePath, 'Não foi possível enviar sua avaliação. Tente novamente.', 'error');
        }

        $this->redirect($moviePath . '#reviews', 'Avaliação enviada com sucesso!');
    }

    /**
     * Nota inteira entre Review::MIN_RATING e Review::MAX_RATING, ou null se inválida.
     */
    private function postRating(): ?int
    {
        $raw = $_POST['rating'] ?? null;

        if (!is_string($raw) || !ctype_digit($raw) || strlen($raw) > 2) {
            return null;
        }

        $rating = (int) $raw;

        return Review::isValidRating($rating) ? $rating : null;
    }

    /**
     * Comentário opcional (null quando vazio). Redireciona com erro se exceder o limite.
     */
    private function postComment(string $errorPath): ?string
    {
        $raw = $_POST['review'] ?? '';

        if (!is_string($raw) || !mb_check_encoding($raw, 'UTF-8')) {
            $this->redirect($errorPath, 'Comentário inválido.', 'error');
        }

        // Normaliza quebras de linha (o navegador envia \r\n, mas o maxlength conta 1 caractere).
        $comment = trim(str_replace(["\r\n", "\r"], "\n", $raw));

        if (mb_strlen($comment) > self::MAX_REVIEW_LENGTH) {
            $this->redirect($errorPath, 'O comentário deve ter no máximo ' . self::MAX_REVIEW_LENGTH . ' caracteres.', 'error');
        }

        return $comment !== '' ? $comment : null;
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
