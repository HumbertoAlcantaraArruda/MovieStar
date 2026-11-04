<?php

declare(strict_types=1);

namespace MovieStar\Tests\Integration;

use MovieStar\DAO\DuplicateReviewException;
use MovieStar\DAO\ReviewDAO;
use MovieStar\Models\Review;
use MovieStar\Tests\Support\DatabaseTestCase;
use PDOException;

class ReviewDAOTest extends DatabaseTestCase
{
    private ReviewDAO $dao;
    private int $ownerId;
    private int $movieId;

    public function setUp(): void
    {
        parent::setUp();
        $this->dao     = new ReviewDAO($this->pdo);
        $this->ownerId = $this->createUser('tmp_test_dono@example.com');
        $this->movieId = $this->createMovie($this->ownerId);
    }

    public function testCreateAndFindByMovieWithAuthor(): void
    {
        $userId = $this->createUser('tmp_test_rev@example.com', 'Bruno');
        $id     = $this->dao->create($this->review($userId, $this->movieId, 9, 'Ótimo <filme>!'));

        $this->assertTrue($id > 0);

        $reviews = $this->dao->findByMovie($this->movieId);
        $this->assertCount(1, $reviews);
        $this->assertSame($id, $reviews[0]->getId());
        $this->assertSame(9, $reviews[0]->getRating());
        $this->assertSame('Ótimo <filme>!', $reviews[0]->getReview());
        $this->assertSame($userId, $reviews[0]->getUserId());
        $this->assertSame($this->movieId, $reviews[0]->getMovieId());
        $this->assertSame('Bruno Teste', $reviews[0]->getAuthorName());
        $this->assertMatches('/^\d{2}\/\d{2}\/\d{4}\z/', $reviews[0]->getCreatedAtFormatted());
    }

    public function testCommentIsOptional(): void
    {
        $this->dao->create($this->review($this->createUser('tmp_test_rev@example.com'), $this->movieId, 5, null));

        $this->assertNull($this->dao->findByMovie($this->movieId)[0]->getReview());
    }

    public function testFindByMovieNewestFirstAndOnlyThatMovie(): void
    {
        $other = $this->createMovie($this->ownerId, 'Outro');
        $u1    = $this->createUser('tmp_test_u1@example.com');
        $u2    = $this->createUser('tmp_test_u2@example.com');

        $first  = $this->dao->create($this->review($u1, $this->movieId, 7));
        $second = $this->dao->create($this->review($u2, $this->movieId, 3));
        $this->dao->create($this->review($u1, $other, 10));

        $ids = array_map(static function (Review $review): int {
            return (int) $review->getId();
        }, $this->dao->findByMovie($this->movieId));

        $this->assertSame([$second, $first], $ids);
        $this->assertSame([], $this->dao->findByMovie(9999));
    }

    public function testOneReviewPerUserPerMovie(): void
    {
        $userId = $this->createUser('tmp_test_rev@example.com');
        $this->dao->create($this->review($userId, $this->movieId, 8));

        $this->assertThrows(DuplicateReviewException::class, function () use ($userId): void {
            $this->dao->create($this->review($userId, $this->movieId, 2));
        });

        $this->assertSame(1, $this->countRows('reviews'));
        $this->assertSame(8.0, $this->dao->getAverageRating($this->movieId), 'a nota original fica');

        // O mesmo usuário pode avaliar outro filme.
        $this->dao->create($this->review($userId, $this->createMovie($this->ownerId, 'Outro'), 4));
        $this->assertSame(2, $this->countRows('reviews'));
    }

    public function testForeignKeyErrorIsNotDuplicate(): void
    {
        $error = $this->assertThrows(PDOException::class, function (): void {
            $this->dao->create($this->review(9999, $this->movieId, 5));
        });

        $this->assertFalse($error instanceof DuplicateReviewException, 'usuário inexistente não é "avaliação duplicada"');
    }

    public function testHasUserReviewed(): void
    {
        $userId = $this->createUser('tmp_test_rev@example.com');

        $this->assertFalse($this->dao->hasUserReviewed($userId, $this->movieId));
        $this->dao->create($this->review($userId, $this->movieId, 6));
        $this->assertTrue($this->dao->hasUserReviewed($userId, $this->movieId));
        $this->assertFalse($this->dao->hasUserReviewed($this->ownerId, $this->movieId));
    }

    public function testAverageRating(): void
    {
        $this->assertNull($this->dao->getAverageRating($this->movieId), 'sem avaliações');

        foreach ([10, 7, 8] as $index => $rating) {
            $this->dao->create($this->review($this->createUser('tmp_test_avg' . $index . '@example.com'), $this->movieId, $rating));
        }

        $average = $this->dao->getAverageRating($this->movieId);
        $this->assertNotNull($average);
        $this->assertTrue(abs((float) $average - 25 / 3) < 0.001, 'média de 10, 7 e 8 (AVG do MySQL tem 4 casas)');
        $this->assertSame('8,3', number_format((float) $average, 1, ',', '.'));
    }

    public function testAverageRatingsInBatch(): void
    {
        $second = $this->createMovie($this->ownerId, 'Segundo');
        $empty  = $this->createMovie($this->ownerId, 'Sem notas');
        $u1     = $this->createUser('tmp_test_u1@example.com');
        $u2     = $this->createUser('tmp_test_u2@example.com');

        $this->dao->create($this->review($u1, $this->movieId, 10));
        $this->dao->create($this->review($u2, $this->movieId, 5));
        $this->dao->create($this->review($u1, $second, 3));

        $averages = $this->dao->getAverageRatings([$this->movieId, $second, $empty, $this->movieId, 9999]);
        ksort($averages);

        $this->assertSame([$this->movieId => 7.5, $second => 3.0], $averages, 'sem notas fica de fora; ids repetidos não duplicam');
        $this->assertSame([], $this->dao->getAverageRatings([]));
    }

    private function review(int $userId, int $movieId, int $rating, ?string $comment = 'Comentário'): Review
    {
        return (new Review())
            ->setRating($rating)
            ->setReview($comment)
            ->setUserId($userId)
            ->setMovieId($movieId);
    }
}
