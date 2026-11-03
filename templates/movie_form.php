<?php
/**
 * Formulário de filme, usado em newmovie.php e editmovie.php.
 *
 * @var \MovieStar\Core\Session $session
 * @var string $baseUrl
 * @var \Closure $e
 * @var \MovieStar\Models\Movie|null $movie filme a editar (null no cadastro)
 * @var string $submitLabel
 */

use MovieStar\Models\Movie;
?>
                <form action="" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= $e($session->csrfToken()) ?>">

                    <div class="form-group">
                        <label for="title">Título:</label>
                        <input type="text" class="form-control" id="title" name="title" maxlength="100" required
                               placeholder="Digite o título do filme"
                               value="<?= $e($movie !== null ? $movie->getTitle() : '') ?>">
                    </div>

                    <div class="form-group">
                        <label for="image">Imagem (opcional):</label>
<?php if ($movie !== null): ?>
                        <div class="mb-2">
                            <img src="<?= $e($baseUrl) ?>img/movies/<?= $e($movie->getImageOrDefault()) ?>" alt="Capa atual" class="img-thumbnail" style="max-height: 150px;">
                        </div>
<?php endif; ?>
                        <input type="file" class="form-control-file" id="image" name="image"
                               accept="image/jpeg,image/png,image/webp">
                        <small class="form-text text-white-50">JPG, PNG ou WEBP, até 2 MB.<?= $movie !== null ? ' Deixe em branco para manter a capa atual.' : '' ?></small>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="category">Categoria:</label>
                            <select class="form-control" id="category" name="category" required>
                                <option value="">Selecione</option>
<?php foreach (Movie::CATEGORIES as $category): ?>
                                <option value="<?= $e($category) ?>"<?= $movie !== null && $movie->getCategory() === $category ? ' selected' : '' ?>><?= $e($category) ?></option>
<?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="length">Duração:</label>
                            <input type="text" class="form-control" id="length" name="length" maxlength="50"
                                   placeholder="Ex.: 2h 10min"
                                   value="<?= $e($movie !== null ? (string) $movie->getLength() : '') ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="trailer">Trailer (opcional):</label>
                        <input type="url" class="form-control" id="trailer" name="trailer" maxlength="255"
                               placeholder="https://www.youtube.com/watch?v=..."
                               value="<?= $e($movie !== null ? (string) $movie->getTrailer() : '') ?>">
                        <small class="form-text text-white-50">Link do YouTube (youtube.com/watch, youtu.be ou youtube.com/embed).</small>
                    </div>

                    <div class="form-group">
                        <label for="description">Descrição:</label>
                        <textarea class="form-control" id="description" name="description" rows="5" maxlength="5000" required
                                  placeholder="Descreva o filme"><?= $e($movie !== null ? (string) $movie->getDescription() : '') ?></textarea>
                    </div>

                    <input type="submit" class="btn btn-light card-btn" value="<?= $e($submitLabel) ?>">
                </form>
