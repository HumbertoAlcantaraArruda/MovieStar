<?php

declare(strict_types=1);

namespace MovieStar\Controllers;

use MovieStar\Core\Auth;
use MovieStar\Core\YouTube;
use MovieStar\DAO\MovieDAO;
use MovieStar\DAO\ReviewDAO;
use MovieStar\DAO\UserDAO;
use MovieStar\Models\Movie;
use MovieStar\Models\Review;
use MovieStar\Models\User;

/**
 * Prepara os dados da página de detalhes de um filme (movie.php?id=).
 */
class MoviePageController
{
    private MovieDAO $movieDAO;
    private UserDAO $userDAO;
    private ReviewDAO $reviewDAO;
    private Auth $auth;

    public function __construct(MovieDAO $movieDAO, UserDAO $userDAO, ReviewDAO $reviewDAO, Auth $auth)
    {
        $this->movieDAO  = $movieDAO;
        $this->userDAO   = $userDAO;
        $this->reviewDAO = $reviewDAO;
        $this->auth      = $auth;
    }

    /**
     * Valida o id e carrega o filme. Em caso de id inválido ou filme inexistente,
     * responde HTTP 404 e retorna null (a página exibe a mensagem amigável).
     *
     * "canReview" indica se o formulário de avaliação deve ser exibido
     * (logado, não é o dono e ainda não avaliou).
     *
     * @return array{
     *     movie: Movie,
     *     author: ?User,
     *     trailerUrl: ?string,
     *     isOwner: bool,
     *     reviews: Review[],
     *     averageRating: ?float,
     *     hasReviewed: bool,
     *     canReview: bool
     * }|null
     */
    public function handleShow(): ?array
    {
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        $movie = is_int($id) ? $this->movieDAO->findById($id) : null;

        if ($movie === null) {
            http_response_code(404);
            return null;
        }

        $movieId     = (int) $movie->getId();
        $user        = $this->auth->getUser();
        $isOwner     = $this->auth->owns($movie->getUserId());
        $hasReviewed = $user !== null && $this->reviewDAO->hasUserReviewed((int) $user->getId(), $movieId);

        return [
            'movie'         => $movie,
            'author'        => $this->userDAO->findById($movie->getUserId()),
            'trailerUrl'    => $this->safeTrailerUrl($movie->getTrailer()),
            'isOwner'       => $isOwner,
            'reviews'       => $this->reviewDAO->findByMovie($movieId),
            'averageRating' => $this->reviewDAO->getAverageRating($movieId),
            'hasReviewed'   => $hasReviewed,
            'canReview'     => $user !== null && !$isOwner && !$hasReviewed,
        ];
    }

    /**
     * Revalida o trailer gravado e devolve a URL de incorporação (modo sem cookies) ou null.
     */
    private function safeTrailerUrl(?string $trailer): ?string
    {
        if ($trailer === null) {
            return null;
        }

        $videoId = YouTube::extractVideoId($trailer);

        return $videoId === null ? null : 'https://www.youtube-nocookie.com/embed/' . $videoId;
    }
}
