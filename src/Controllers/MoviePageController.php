<?php

declare(strict_types=1);

namespace MovieStar\Controllers;

use MovieStar\Core\Auth;
use MovieStar\Core\YouTube;
use MovieStar\DAO\MovieDAO;
use MovieStar\DAO\UserDAO;
use MovieStar\Models\Movie;
use MovieStar\Models\User;

/**
 * Prepara os dados da página de detalhes de um filme (movie.php?id=).
 */
class MoviePageController
{
    private MovieDAO $movieDAO;
    private UserDAO $userDAO;
    private Auth $auth;

    public function __construct(MovieDAO $movieDAO, UserDAO $userDAO, Auth $auth)
    {
        $this->movieDAO = $movieDAO;
        $this->userDAO  = $userDAO;
        $this->auth     = $auth;
    }

    /**
     * Valida o id e carrega o filme. Em caso de id inválido ou filme inexistente,
     * responde HTTP 404 e retorna null (a página exibe a mensagem amigável).
     *
     * @return array{movie: Movie, author: ?User, trailerUrl: ?string, isOwner: bool}|null
     */
    public function handleShow(): ?array
    {
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        $movie = is_int($id) ? $this->movieDAO->findById($id) : null;

        if ($movie === null) {
            http_response_code(404);
            return null;
        }

        return [
            'movie'      => $movie,
            'author'     => $this->userDAO->findById($movie->getUserId()),
            'trailerUrl' => $this->safeTrailerUrl($movie->getTrailer()),
            'isOwner'    => $this->auth->owns($movie->getUserId()),
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
