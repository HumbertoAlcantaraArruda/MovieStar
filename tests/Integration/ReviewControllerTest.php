<?php

declare(strict_types=1);

namespace MovieStar\Tests\Integration;

use MovieStar\DAO\ReviewDAO;
use MovieStar\Tests\Support\ControllerTestCase;

class ReviewControllerTest extends ControllerTestCase
{
    private int $ownerId;
    private int $reviewerId;
    private int $movieId;

    public function setUp(): void
    {
        parent::setUp();
        $this->ownerId    = $this->createUser('tmp_test_dono@example.com');
        $this->reviewerId = $this->createUser('tmp_test_rev@example.com');
        $this->movieId    = $this->createMovie($this->ownerId);
    }

    public function testRequiresLogin(): void
    {
        $result = $this->review(['rating' => '8'], null);

        $this->assertRedirect($result, 'auth.php', 'Faça login para continuar.');
    }

    public function testRejectsInvalidCsrf(): void
    {
        $result = $this->review(['rating' => '8', 'csrf_token' => ['x']]);

        $this->assertRedirect($result, 'movie.php?id=' . $this->movieId, 'Requisição inválida. Tente novamente.');
        $this->assertSame(0, $this->countRows('reviews'));
    }

    public function testMovieNotFound(): void
    {
        $result = $this->review(['rating' => '8'], $this->reviewerId, '99999');

        $this->assertRedirect($result, '', 'Filme não encontrado.');
    }

    public function testOwnerCannotReview(): void
    {
        $result = $this->review(['rating' => '8'], $this->ownerId);

        $this->assertRedirect($result, 'movie.php?id=' . $this->movieId, 'Você não pode avaliar um filme que cadastrou.');
        $this->assertSame(0, $this->countRows('reviews'));
    }

    public function testInvalidRatings(): void
    {
        foreach (['', '0', '11', '-1', '5.5', '8 ', 'abc', '007', ['8']] as $rating) {
            $result = $this->review(['rating' => $rating]);

            $this->assertRedirect($result, 'movie.php?id=' . $this->movieId, 'Escolha uma nota de 1 a 10.');
        }

        $this->assertSame(0, $this->countRows('reviews'));
    }

    public function testCommentTooLong(): void
    {
        $result = $this->review(['rating' => '8', 'review' => str_repeat('é', 2001)]);

        $this->assertRedirect($result, 'movie.php?id=' . $this->movieId, 'O comentário deve ter no máximo 2000 caracteres.');
        $this->assertSame('8', $result['session']['old_input']['data']['rating'] ?? null, 'nota volta para o formulário');
    }

    public function testCommentLineBreaksAreNormalized(): void
    {
        // 2000 caracteres contando "\r\n" como um só (como o maxlength do navegador).
        $comment = str_repeat("abc\r\n", 500);
        $result  = $this->review(['rating' => '10', 'review' => $comment]);

        $this->assertRedirect($result, 'movie.php?id=' . $this->movieId . '#reviews', 'Avaliação enviada com sucesso!', 'success');
        $this->assertSame(rtrim(str_repeat("abc\n", 500)), (new ReviewDAO($this->pdo))->findByMovie($this->movieId)[0]->getReview());
    }

    public function testSuccessThenDuplicate(): void
    {
        $first = $this->review(['rating' => '7', 'review' => '  Bom filme.  ']);
        $this->assertRedirect($first, 'movie.php?id=' . $this->movieId . '#reviews', 'Avaliação enviada com sucesso!', 'success');

        $reviews = (new ReviewDAO($this->pdo))->findByMovie($this->movieId);
        $this->assertCount(1, $reviews);
        $this->assertSame(7, $reviews[0]->getRating());
        $this->assertSame('Bom filme.', $reviews[0]->getReview());
        $this->assertSame($this->reviewerId, $reviews[0]->getUserId());

        $second = $this->review(['rating' => '1']);
        $this->assertRedirect($second, 'movie.php?id=' . $this->movieId, 'Você já avaliou este filme.');
        $this->assertSame(1, $this->countRows('reviews'));
    }

    public function testEmptyCommentIsStoredAsNull(): void
    {
        $this->review(['rating' => '5', 'review' => "  \r\n "]);

        $this->assertNull((new ReviewDAO($this->pdo))->findByMovie($this->movieId)[0]->getReview());
    }

    /**
     * @param array<string, mixed> $post
     * @return array{location: ?string, flash: ?array{msg: string, type: string}, session: array<string, mixed>, returned: bool}
     */
    private function review(array $post, ?int $userId = -1, ?string $movieId = null): array
    {
        return $this->post('review', $post, [
            'userId' => $userId === -1 ? $this->reviewerId : $userId,
            'query'  => ['id' => $movieId ?? (string) $this->movieId],
        ]);
    }
}
