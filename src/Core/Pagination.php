<?php

declare(strict_types=1);

namespace MovieStar\Core;

class Pagination
{
    private int $total;
    private int $perPage;
    private int $page;

    /**
     * @param int|null $requestedPage Página pedida; null lê $_GET['page'] (inteiro >= 1, senão 1).
     */
    public function __construct(int $total, int $perPage, ?int $requestedPage = null)
    {
        $this->total   = max(0, $total);
        $this->perPage = max(1, $perPage);

        $page = $requestedPage ?? self::pageFromRequest();
        $this->page = min(max(1, $page), $this->getTotalPages());
    }

    private static function pageFromRequest(): int
    {
        $raw  = $_GET['page'] ?? null;
        $page = is_string($raw)
            ? filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
            : false;

        return is_int($page) ? $page : 1;
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function getPerPage(): int
    {
        return $this->perPage;
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function getOffset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    public function getTotalPages(): int
    {
        return max(1, (int) ceil($this->total / $this->perPage));
    }
}
