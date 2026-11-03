<?php
/**
 * Paginação (Bootstrap 4). Espera $pagination (Core\Pagination) e, opcionalmente,
 * $paginationParams (parâmetros da query string a preservar, ex.: ['q' => 'x']).
 *
 * @var \MovieStar\Core\Pagination $pagination
 * @var array<string, scalar> $paginationParams
 * @var \Closure $e
 */
$totalPages = $pagination->getTotalPages();

if ($totalPages > 1):
    $current = $pagination->getPage();
    $extra   = $paginationParams ?? [];
    unset($extra['page']);
    $pageUrl = static function (int $n) use ($extra): string {
        return '?' . http_build_query($extra + ['page' => $n]);
    };
    $start = max(1, $current - 2);
    $end   = min($totalPages, $current + 2);
?>
        <nav aria-label="Paginação" class="mt-3">
            <ul class="pagination justify-content-center flex-wrap">
                <li class="page-item<?= $current <= 1 ? ' disabled' : '' ?>">
                    <a class="page-link" href="<?= $e($pageUrl(max(1, $current - 1))) ?>" aria-label="Página anterior"<?= $current <= 1 ? ' tabindex="-1" aria-disabled="true"' : '' ?>>&laquo;</a>
                </li>
<?php for ($n = $start; $n <= $end; $n++): ?>
                <li class="page-item<?= $n === $current ? ' active' : '' ?>"<?= $n === $current ? ' aria-current="page"' : '' ?>>
                    <a class="page-link" href="<?= $e($pageUrl($n)) ?>" aria-label="Página <?= $n ?>"><?= $n ?></a>
                </li>
<?php endfor; ?>
                <li class="page-item<?= $current >= $totalPages ? ' disabled' : '' ?>">
                    <a class="page-link" href="<?= $e($pageUrl(min($totalPages, $current + 1))) ?>" aria-label="Próxima página"<?= $current >= $totalPages ? ' tabindex="-1" aria-disabled="true"' : '' ?>>&raquo;</a>
                </li>
            </ul>
        </nav>
<?php endif; ?>
