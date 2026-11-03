<?php

declare(strict_types=1);

namespace MovieStar\Controllers;

use MovieStar\Core\Auth;
use MovieStar\Core\ImageUploader;
use MovieStar\Core\Pagination;
use MovieStar\Core\Session;
use MovieStar\Core\UploadException;
use MovieStar\Core\UserRules;
use MovieStar\DAO\MovieDAO;
use MovieStar\DAO\ReviewDAO;
use MovieStar\DAO\UserDAO;
use MovieStar\Models\Movie;
use MovieStar\Models\User;
use PDOException;

/**
 * Perfil do usuário: página pública (profile.php) e edição de dados, foto e senha (editprofile.php).
 */
class ProfileController
{
    private const EDIT_PATH = 'editprofile.php';
    private const MOVIES_PER_PAGE = 12;

    private UserDAO $userDAO;
    private MovieDAO $movieDAO;
    private ReviewDAO $reviewDAO;
    private ImageUploader $imageUploader;
    private Session $session;
    private Auth $auth;
    private string $baseUrl;

    public function __construct(
        UserDAO $userDAO,
        MovieDAO $movieDAO,
        ReviewDAO $reviewDAO,
        ImageUploader $imageUploader,
        Session $session,
        Auth $auth,
        string $baseUrl
    ) {
        $this->userDAO       = $userDAO;
        $this->movieDAO      = $movieDAO;
        $this->reviewDAO     = $reviewDAO;
        $this->imageUploader = $imageUploader;
        $this->session       = $session;
        $this->auth          = $auth;
        $this->baseUrl       = $baseUrl;
    }

    /**
     * Página de edição: exige login, trata o POST (dados ou senha) e devolve o usuário para o formulário.
     */
    public function handleEdit(): User
    {
        $user = $this->auth->requireLogin();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Corpo maior que post_max_size: o PHP descarta $_POST e $_FILES inteiros.
            if (empty($_POST) && empty($_FILES) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
                $this->redirect(self::EDIT_PATH, 'O envio excede o tamanho máximo permitido.', 'error');
            }

            if (!$this->session->isValidCsrfToken($_POST['csrf_token'] ?? null)) {
                $this->redirect(self::EDIT_PATH, 'Requisição inválida. Tente novamente.', 'error');
            }

            switch ($this->postString('type')) {
                case 'profile':
                    $this->updateProfile($user);
                    break;
                case 'password':
                    $this->updatePassword($user);
                    break;
                default:
                    $this->redirect(self::EDIT_PATH, 'Requisição inválida. Tente novamente.', 'error');
            }
        }

        return $user;
    }

    /**
     * Dados do perfil público (profile.php?id=). Id inválido ou inexistente: responde 404 e retorna null.
     *
     * @return array{profile: User, movies: Movie[], pagination: Pagination, ratings: array<int, float>, isOwner: bool}|null
     */
    public function handleShow(): ?array
    {
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        $profile = is_int($id) ? $this->userDAO->findById($id) : null;

        if ($profile === null) {
            http_response_code(404);
            return null;
        }

        $profileId  = (int) $profile->getId();
        $pagination = new Pagination($this->movieDAO->countByUser($profileId), self::MOVIES_PER_PAGE);
        $movies     = $this->movieDAO->findByUser($profileId, self::MOVIES_PER_PAGE, $pagination->getOffset());
        $movieIds = [];

        foreach ($movies as $movie) {
            $movieIds[] = (int) $movie->getId();
        }

        return [
            'profile' => $profile,
            'movies'  => $movies,
            'pagination' => $pagination,
            'ratings' => $this->reviewDAO->getAverageRatings($movieIds),
            'isOwner' => $this->auth->owns($profile->getId()),
        ];
    }

    private function updateProfile(User $user): void
    {
        $name     = $this->postString('name');
        $lastname = $this->postString('lastname');
        $bio      = $this->postString('bio');

        if ($name === '' || $lastname === '') {
            $this->redirect(self::EDIT_PATH, 'Preencha nome e sobrenome.', 'error');
        }

        $nameError = UserRules::nameError($name, $lastname);

        if ($nameError !== null) {
            $this->redirect(self::EDIT_PATH, $nameError, 'error');
        }

        if (mb_strlen($bio) > UserRules::MAX_BIO_LENGTH) {
            $this->redirect(self::EDIT_PATH, 'A bio deve ter no máximo ' . UserRules::MAX_BIO_LENGTH . ' caracteres.', 'error');
        }

        $oldImage = $user->getImage();
        $newImage = null;
        $file     = isset($_FILES['image']) && is_array($_FILES['image']) ? $_FILES['image'] : null;

        if ($this->imageUploader->wasSent($file)) {
            try {
                $newImage = $this->imageUploader->upload($file);
            } catch (UploadException $e) {
                $this->redirect(self::EDIT_PATH, $e->getMessage(), 'error');
            }
        }

        $user
            ->setName($name)
            ->setLastname($lastname)
            ->setBio($bio !== '' ? $bio : null);

        if ($newImage !== null) {
            $user->setImage($newImage);
        }

        try {
            $this->userDAO->update($user);
        } catch (PDOException $e) {
            $this->imageUploader->delete($newImage);
            error_log('Falha ao atualizar perfil: ' . $e->getMessage());
            $this->redirect(self::EDIT_PATH, 'Não foi possível atualizar o perfil. Tente novamente.', 'error');
        }

        // Só apaga a foto antiga depois que o UPDATE deu certo.
        if ($newImage !== null) {
            $this->imageUploader->delete($oldImage);
        }

        $this->redirect('profile.php?id=' . (int) $user->getId(), 'Perfil atualizado com sucesso!');
    }

    private function updatePassword(User $user): void
    {
        // Senhas não passam por trim(): espaços fazem parte da senha.
        $current  = $this->rawPostString('current_password');
        $password = $this->rawPostString('password');
        $confirm  = $this->rawPostString('confirmpassword');

        if ($current === '' || $password === '') {
            $this->redirect(self::EDIT_PATH, 'Preencha a senha atual e a nova senha.', 'error');
        }

        if (!password_verify($current, $user->getPassword())) {
            $this->redirect(self::EDIT_PATH, 'A senha atual está incorreta.', 'error');
        }

        $passwordError = UserRules::passwordError($password, $confirm);

        if ($passwordError !== null) {
            $this->redirect(self::EDIT_PATH, $passwordError, 'error');
        }

        try {
            $this->userDAO->updatePassword((int) $user->getId(), password_hash($password, PASSWORD_DEFAULT));
        } catch (PDOException $e) {
            error_log('Falha ao atualizar senha: ' . $e->getMessage());
            $this->redirect(self::EDIT_PATH, 'Não foi possível alterar a senha. Tente novamente.', 'error');
        }

        // Regenera o id da sessão após a troca de credencial.
        $this->session->login((int) $user->getId());
        $this->redirect(self::EDIT_PATH, 'Senha alterada com sucesso!');
    }

    /**
     * Valor de $_POST como string aparada (arrays, ausentes e UTF-8 inválido viram '').
     */
    private function postString(string $key): string
    {
        return trim($this->rawPostString($key));
    }

    private function rawPostString(string $key): string
    {
        $value = $_POST[$key] ?? '';

        return is_string($value) && mb_check_encoding($value, 'UTF-8') ? $value : '';
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
