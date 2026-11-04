<?php

declare(strict_types=1);

namespace MovieStar\Tests\Integration;

use MovieStar\DAO\MovieDAO;
use MovieStar\Models\Movie;
use MovieStar\Tests\Support\DatabaseTestCase;

class MovieDAOTest extends DatabaseTestCase
{
    private MovieDAO $dao;
    private int $userId;

    public function setUp(): void
    {
        parent::setUp();
        $this->dao    = new MovieDAO($this->pdo);
        $this->userId = $this->createUser();
    }

    public function testCreateAndFindById(): void
    {
        $id = $this->dao->create(
            (new Movie())
                ->setTitle('Cidade de Deus')
                ->setDescription('Descrição com "aspas" e <tags>.')
                ->setImage(str_repeat('a', 32) . '.jpg')
                ->setTrailer('https://www.youtube.com/embed/dQw4w9WgXcQ')
                ->setCategory('Drama')
                ->setLength('2h 10min')
                ->setUserId($this->userId)
        );

        $movie = $this->dao->findById($id);

        $this->assertNotNull($movie);
        $this->assertSame($id, $movie->getId());
        $this->assertSame('Cidade de Deus', $movie->getTitle());
        $this->assertSame('Descrição com "aspas" e <tags>.', $movie->getDescription());
        $this->assertSame(str_repeat('a', 32) . '.jpg', $movie->getImage());
        $this->assertSame('https://www.youtube.com/embed/dQw4w9WgXcQ', $movie->getTrailer());
        $this->assertSame('Drama', $movie->getCategory());
        $this->assertSame('2h 10min', $movie->getLength());
        $this->assertSame($this->userId, $movie->getUserId());
        $this->assertMatches('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\z/', (string) $movie->getCreatedAt());
    }

    public function testOptionalFieldsAreNull(): void
    {
        $movie = $this->dao->findById($this->createMovie($this->userId));

        $this->assertNotNull($movie);
        $this->assertNull($movie->getImage());
        $this->assertNull($movie->getTrailer());
        $this->assertNull($movie->getLength());
        $this->assertSame(Movie::DEFAULT_IMAGE, $movie->getImageOrDefault());
    }

    public function testFindMissingReturnsNull(): void
    {
        $this->assertNull($this->dao->findById(12345));
    }

    public function testUpdate(): void
    {
        $id    = $this->createMovie($this->userId, 'Antigo');
        $other = $this->createMovie($this->userId, 'Outro');
        $movie = $this->dao->findById($id);
        $this->assertNotNull($movie);

        $movie->setTitle('Novo')->setCategory('Terror')->setLength(null)->setImage('x.png');
        $this->dao->update($movie);

        $reloaded = $this->dao->findById($id);
        $this->assertNotNull($reloaded);
        $this->assertSame('Novo', $reloaded->getTitle());
        $this->assertSame('Terror', $reloaded->getCategory());
        $this->assertSame('x.png', $reloaded->getImage());

        $untouched = $this->dao->findById($other);
        $this->assertNotNull($untouched);
        $this->assertSame('Outro', $untouched->getTitle(), 'UPDATE restrito ao id');
    }

    public function testDeleteCascadesReviews(): void
    {
        $id       = $this->createMovie($this->userId);
        $keep     = $this->createMovie($this->userId, 'Fica');
        $reviewer = $this->createUser('tmp_test_rev@example.com');
        $this->pdo->exec('INSERT INTO reviews (rating, users_id, movies_id) VALUES (8, ' . $reviewer . ', ' . $id . '), (6, ' . $reviewer . ', ' . $keep . ')');

        $this->dao->delete($id);

        $this->assertNull($this->dao->findById($id));
        $this->assertNotNull($this->dao->findById($keep));
        $this->assertSame(1, $this->countRows('reviews'), 'avaliações do filme excluído saem em cascata');
    }

    public function testDeletingUserCascadesMovies(): void
    {
        $this->createMovie($this->userId);
        $this->pdo->exec('DELETE FROM users WHERE id = ' . $this->userId);

        $this->assertSame(0, $this->dao->countAll());
    }

    public function testFindLatestOrderAndPagination(): void
    {
        $ids = [];

        for ($i = 1; $i <= 5; $i++) {
            $ids[] = $this->createMovie($this->userId, 'Filme ' . $i);
        }

        // Mesmo segundo de criação: o desempate é pelo id (mais novo primeiro).
        $this->assertSame([$ids[4], $ids[3]], $this->ids($this->dao->findLatest(2)));
        $this->assertSame([$ids[2], $ids[1]], $this->ids($this->dao->findLatest(2, 2)));
        $this->assertSame([$ids[0]], $this->ids($this->dao->findLatest(2, 4)));
        $this->assertSame([], $this->dao->findLatest(2, 10));
        $this->assertSame(5, $this->dao->countAll());

        // created_at manda antes do id.
        $this->pdo->exec("UPDATE movies SET created_at = '2030-01-01 00:00:00' WHERE id = " . $ids[0]);
        $this->assertSame([$ids[0]], $this->ids($this->dao->findLatest(1)));
    }

    public function testFindByCategoryAndCategoriesWithMovies(): void
    {
        $drama1 = $this->createMovie($this->userId, 'D1', 'x', 'Drama');
        $drama2 = $this->createMovie($this->userId, 'D2', 'x', 'Drama');
        $this->createMovie($this->userId, 'T1', 'x', 'Terror');
        $acao = $this->createMovie($this->userId, 'A1', 'x', 'Ação');

        $this->assertSame([$drama2, $drama1], $this->ids($this->dao->findByCategory('Drama', 10)));
        $this->assertSame([$drama2], $this->ids($this->dao->findByCategory('Drama', 1)));
        $this->assertSame([$acao], $this->ids($this->dao->findByCategory('Ação', 10)));
        $this->assertSame([], $this->dao->findByCategory('Romance', 10));

        $categories = $this->dao->findCategoriesWithMovies();
        sort($categories);
        $this->assertSame(['Ação', 'Drama', 'Terror'], $categories);
    }

    public function testFindByUserWithAndWithoutLimit(): void
    {
        $other = $this->createUser('tmp_test_outro@example.com');
        $mine  = [];

        for ($i = 1; $i <= 3; $i++) {
            $mine[] = $this->createMovie($this->userId, 'Meu ' . $i);
            $this->createMovie($other, 'Dele ' . $i);
        }

        $this->assertSame(array_reverse($mine), $this->ids($this->dao->findByUser($this->userId)));
        $this->assertSame([$mine[2], $mine[1]], $this->ids($this->dao->findByUser($this->userId, 2)));
        $this->assertSame([$mine[0]], $this->ids($this->dao->findByUser($this->userId, 2, 2)));
        $this->assertSame(3, $this->dao->countByUser($this->userId));
        $this->assertSame(0, $this->dao->countByUser(999));
        $this->assertSame([], $this->dao->findByUser(999));
    }

    public function testSearchByTitleOrDescription(): void
    {
        $title = $this->createMovie($this->userId, 'O Poderoso Chefão', 'Família mafiosa.');
        $desc  = $this->createMovie($this->userId, 'Outro', 'Um chefão do crime.');
        $this->createMovie($this->userId, 'Sem relação', 'Nada a ver.');

        $this->assertSame([$desc, $title], $this->ids($this->dao->search('chefão', 10)));
        $this->assertSame(2, $this->dao->countSearch('chefão'));
        $this->assertSame([$title], $this->ids($this->dao->search('PODEROSO', 10)), 'sem diferenciar maiúsculas');
        $this->assertSame([$title], $this->ids($this->dao->search('mafiosa', 10)));
        $this->assertSame([], $this->dao->search('inexistente', 10));
        $this->assertSame(0, $this->dao->countSearch('inexistente'));
    }

    public function testSearchPagination(): void
    {
        $ids = [];

        for ($i = 1; $i <= 5; $i++) {
            $ids[] = $this->createMovie($this->userId, 'Saga parte ' . $i);
        }

        $this->assertSame(5, $this->dao->countSearch('saga'));
        $this->assertSame([$ids[4], $ids[3]], $this->ids($this->dao->search('saga', 2)));
        $this->assertSame([$ids[2], $ids[1]], $this->ids($this->dao->search('saga', 2, 2)));
        $this->assertSame([$ids[0]], $this->ids($this->dao->search('saga', 2, 4)));
    }

    public function testSearchTreatsWildcardsAsLiterals(): void
    {
        $percent    = $this->createMovie($this->userId, '100% Lobo', 'x');
        $underscore = $this->createMovie($this->userId, 'Arquivo_X', 'x');
        $bang       = $this->createMovie($this->userId, 'Socorro!', 'x');
        $this->createMovie($this->userId, '100 Lobos', 'x');
        $this->createMovie($this->userId, 'ArquivoZX', 'x');

        $this->assertSame([$percent], $this->ids($this->dao->search('%', 10)), '"%" não casa com tudo');
        $this->assertSame([$percent], $this->ids($this->dao->search('100%', 10)));
        $this->assertSame(1, $this->dao->countSearch('%'));
        $this->assertSame([$underscore], $this->ids($this->dao->search('_', 10)), '"_" não casa com qualquer caractere');
        $this->assertSame([$underscore], $this->ids($this->dao->search('o_X', 10)));
        $this->assertSame(1, $this->dao->countSearch('_'));
        // O caractere de escape também é literal. (Um termo que COMEÇA com "!" não encontra nada no
        // MySQL 5.7 com utf8mb4_unicode_ci: bug do MySQL com o escape logo após o "%" inicial.)
        $this->assertSame([$bang], $this->ids($this->dao->search('socorro!', 10)), '"!" literal');
        $this->assertSame([], $this->dao->search('socorro!!', 10));
        $this->assertSame([], $this->dao->search('Lobo!%', 10));
        $this->assertSame([], $this->dao->search("' OR 1=1 -- ", 10), 'termo é dado, não SQL');
    }

    /**
     * @param Movie[] $movies
     * @return int[]
     */
    private function ids(array $movies): array
    {
        return array_map(static function (Movie $movie): int {
            return (int) $movie->getId();
        }, $movies);
    }
}
