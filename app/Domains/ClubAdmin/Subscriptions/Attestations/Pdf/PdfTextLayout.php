<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Subscriptions\Attestations\Pdf;

/**
 * Every word of a template and where it sits, in millimetres.
 *
 * @phpstan-type PageSize array{width: float, height: float}
 */
final readonly class PdfTextLayout
{
    /**
     * @param  array<int, PdfWord>  $words
     * @param  array<int, PageSize>  $pages  Keyed by 1-based page number.
     */
    public function __construct(
        public array $words,
        public array $pages,
    ) {}

    public function pageCount(): int
    {
        return count($this->pages);
    }

    /**
     * @return PageSize
     */
    public function pageSize(int $page = 1): array
    {
        return $this->pages[$page] ?? ['width' => 210.0, 'height' => 297.0];
    }

    /**
     * The words of one page, in reading order.
     *
     * @return array<int, PdfWord>
     */
    public function wordsOnPage(int $page): array
    {
        return array_values(array_filter(
            $this->words,
            static fn (PdfWord $word): bool => $word->page === $page,
        ));
    }
}
