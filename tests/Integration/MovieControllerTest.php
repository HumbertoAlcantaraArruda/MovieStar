<?php

declare(strict_types=1);

namespace MovieStar\Tests\Integration;

use MovieStar\DAO\MovieDAO;
use MovieStar\Tests\Support\ControllerTestCase;
use MovieStar\Tests\Support\ImageFactory;
use MovieStar\Tests\Support\TempDirectory;

class MovieControllerTest extends ControllerTestCase
{
    private int $userId;

    public function setUp(): void
    {
        parent::setUp();
        $this->userId = $this->createUser('tmp_test_movie@example.com');
    }

    public function testRequiresLogin(): void
    {
        $result = $this->post('movie_new', $this->movieData());

        $this->assertRedirect($result, 'auth.php', 'Faça login para continuar.');
        $this->assertSame(0, $this->countRows('movies'));
    }

    public function testRejectsInvalidCsrf(): void
    {
        $result = $this->post('movie_new', ['csrf_token' => 'x'] + $this->movieData(), ['userId' => $this->userId]);

        $this->assertRedirect($result, 'newmovie.php', 'Requisição inválida. Tente novamente.');
        $this->assertSame(0, $this->countRows('movies'));
    }

    public function testCreateValidations(): void
    {
        $cases = [
            'Preencha título, descrição e categoria.'            => ['title' => '  '],
            'O título deve ter no máximo 100 caracteres.'         => ['title' => str_repeat('é', 101)],
            'A descrição deve ter no máximo 5000 caracteres.'     => ['description' => str_repeat('a', 5001)],
            'Selecione uma categoria válida.'                     => ['category' => 'Inventada'],
            'A duração deve ter no máximo 50 caracteres.'         => ['length' => str_repeat('1', 51)],
            'Informe um link válido do YouTube para o trailer.'  => ['trailer' => 'https://evil.com/watch?v=dQw4w9WgXcQ'],
        ];

        foreach ($cases as $message => $override) {
            $result = $this->post('movie_new', $override + $this->movieData(), ['userId' => $this->userId]);

            $this->assertRedirect($result, 'newmovie.php', $message);
        }

        $this->assertSame(0, $this->countRows('movies'));
    }

    public function testCreateErrorKeepsTypedValues(): void
    {
        $result = $this->post('movie_new', ['category' => 'Inventada'] + $this->movieData(), ['userId' => $this->userId]);

        $this->assertSame('newmovie.php', $result['session']['old_input']['form'] ?? null);
        $this->assertSame('Meu Filme', $result['session']['old_input']['data']['title'] ?? null);
    }

    public function testCreateSuccessNormalizesTrailer(): void
    {
        $result = $this->post('movie_new', $this->movieData() + ['users_id' => '999'], ['userId' => $this->userId]);

        $this->assertRedirect($result, 'dashboard.php', 'Filme cadastrado com sucesso!', 'success');

        $movies = (new MovieDAO($this->pdo))->findByUser($this->userId);
        $this->assertCount(1, $movies);
        $this->assertSame('Meu Filme', $movies[0]->getTitle());
        $this->assertSame('Ficção Científica', $movies[0]->getCategory());
        $this->assertSame('https://www.youtube.com/embed/dQw4w9WgXcQ', $movies[0]->getTrailer());
        $this->assertSame('2h', $movies[0]->getLength());
        $this->assertNull($movies[0]->getImage(), 'sem capa enviada');
        $this->assertSame($this->userId, $movies[0]->getUserId(), 'dono é o usuário logado, nunca o formulário');
    }

    public function testCreateWithImageAndRejectedImage(): void
    {
        $ok = $this->post('movie_new', $this->movieData(), [
            'userId' => $this->userId,
            'files'  => ['image' => ['capa.png', ImageFactory::png(), 'image/png']],
        ]);
        $this->assertRedirect($ok, 'dashboard.php', 'Filme cadastrado com sucesso!', 'success');

        $movies = (new MovieDAO($this->pdo))->findByUser($this->userId);
        $this->assertCount(1, $movies);
        $this->assertSame([$movies[0]->getImage()], TempDirectory::listFiles($this->uploadDir), 'capa gravada');

        $bad = $this->post('movie_new', $this->movieData(), [
            'userId' => $this->userId,
            'files'  => ['image' => ['capa.jpg', '<?php echo 1;', 'image/jpeg']],
        ]);
        $this->assertRedirect($bad, 'newmovie.php', 'Formato de imagem não suportado. Envie um arquivo JPG, PNG ou WEBP.');
        $this->assertSame(1, $this->countRows('movies'));
    }

    public function testEditOnlyByOwner(): void
    {
        $other   = $this->createUser('tmp_test_outro@example.com');
        $movieId = $this->createMovie($other, 'Dele');

        $foreign = $this->post('movie_edit', $this->movieData(), ['userId' => $this->userId, 'query' => ['id' => (string) $movieId]]);
        $this->assertRedirect($foreign, 'dashboard.php', 'Filme não encontrado.');

        $missing = $this->post('movie_edit', $this->movieData(), ['userId' => $this->userId, 'query' => ['id' => '99999']]);
        $this->assertRedirect($missing, 'dashboard.php', 'Filme não encontrado.', 'error');

        $invalid = $this->post('movie_edit', $this->movieData(), ['userId' => $this->userId, 'query' => ['id' => '1 OR 1=1']]);
        $this->assertRedirect($invalid, 'dashboard.php', 'Filme não encontrado.', 'error');

        $movie = (new MovieDAO($this->pdo))->findById($movieId);
        $this->assertNotNull($movie);
        $this->assertSame('Dele', $movie->getTitle(), 'filme de outro usuário não muda');
    }

    public function testEditReplacesImageAndDeletesOldOne(): void
    {
        $oldImage = str_repeat('c3', 16) . '.png';
        file_put_contents($this->uploadDir . DIRECTORY_SEPARATOR . $oldImage, ImageFactory::png());

        $movieId = $this->createMovie($this->userId, 'Antigo');
        $this->pdo->exec("UPDATE movies SET image = '" . $oldImage . "' WHERE id = " . $movieId);

        $result = $this->post('movie_edit', ['title' => 'Atualizado'] + $this->movieData(), [
            'userId' => $this->userId,
            'query'  => ['id' => (string) $movieId],
            'files'  => ['image' => ['nova.webp', ImageFactory::webp(), 'image/webp']],
        ]);

        $this->assertRedirect($result, 'dashboard.php', 'Filme atualizado com sucesso!', 'success');

        $movie = (new MovieDAO($this->pdo))->findById($movieId);
        $this->assertNotNull($movie);
        $this->assertSame('Atualizado', $movie->getTitle());
        $this->assertMatches('/^[a-f0-9]{32}\.webp\z/', (string) $movie->getImage());
        $this->assertSame([$movie->getImage()], TempDirectory::listFiles($this->uploadDir), 'capa antiga apagada');
    }

    public function testEditValidationErrorKeepsMovie(): void
    {
        $movieId = $this->createMovie($this->userId, 'Original');

        $result = $this->post('movie_edit', ['trailer' => 'nao-e-link'] + $this->movieData(), [
            'userId' => $this->userId,
            'query'  => ['id' => (string) $movieId],
        ]);

        $this->assertRedirect($result, 'editmovie.php?id=' . $movieId, 'Informe um link válido do YouTube para o trailer.');
        $this->assertSame('editmovie.php?id=' . $movieId, $result['session']['old_input']['form'] ?? null);

        $movie = (new MovieDAO($this->pdo))->findById($movieId);
        $this->assertNotNull($movie);
        $this->assertSame('Original', $movie->getTitle());
    }

    public function testDeleteFromDashboard(): void
    {
        $image = str_repeat('d4', 16) . '.jpg';
        file_put_contents($this->uploadDir . DIRECTORY_SEPARATOR . $image, 'x');

        $mine = $this->createMovie($this->userId, 'Meu');
        $this->pdo->exec("UPDATE movies SET image = '" . $image . "' WHERE id = " . $mine);
        $theirs = $this->createMovie($this->createUser('tmp_test_outro@example.com'), 'Dele');

        $denied = $this->post('dashboard', ['id' => (string) $theirs], ['userId' => $this->userId]);
        $this->assertRedirect($denied, 'dashboard.php', 'Filme não encontrado.');

        $noCsrf = $this->post('dashboard', ['id' => (string) $mine, 'csrf_token' => null], ['userId' => $this->userId]);
        $this->assertRedirect($noCsrf, 'dashboard.php', 'Requisição inválida. Tente novamente.');
        $this->assertSame(2, $this->countRows('movies'));

        $deleted = $this->post('dashboard', ['id' => (string) $mine], ['userId' => $this->userId]);
        $this->assertRedirect($deleted, 'dashboard.php', 'Filme excluído com sucesso!', 'success');
        $this->assertSame(1, $this->countRows('movies'));
        $this->assertSame([], TempDirectory::listFiles($this->uploadDir), 'capa apagada junto com o filme');
    }

    /**
     * @return array<string, string>
     */
    private function movieData(): array
    {
        return [
            'title'       => 'Meu Filme',
            'description' => 'Uma descrição.',
            'category'    => 'Ficção Científica',
            'length'      => '2h',
            'trailer'     => 'https://youtu.be/dQw4w9WgXcQ',
        ];
    }
}
