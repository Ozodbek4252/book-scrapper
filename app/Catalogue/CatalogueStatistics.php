<?php

declare(strict_types=1);

namespace App\Catalogue;

use App\Models\Author;
use App\Models\Book;
use App\Models\BookSource;
use App\Models\Publisher;
use Illuminate\Support\Collection;

/**
 * The numbers the dashboard reports on.
 *
 * Kept out of the controller so the same figures can be asserted in tests and
 * later reused by the API without going through HTTP.
 */
final readonly class CatalogueStatistics
{
    private const RECENT_DAYS = 7;

    /**
     * @return array{
     *     books: int,
     *     with_isbn: int,
     *     with_isbn_percent: float,
     *     with_cover: int,
     *     with_cover_percent: float,
     *     verified: int,
     *     unverified: int,
     *     added_recently: int,
     *     recent_days: int,
     *     authors: int,
     *     publishers: int,
     * }
     */
    public function summary(): array
    {
        // One pass over books rather than five separate counts. toBase() keeps
        // Eloquent casts off the aggregate row: a `verified` cast to boolean
        // would turn a count of 8 into true, and then into 1.
        $books = Book::query()
            ->toBase()
            ->selectRaw('count(*) as total')
            ->selectRaw('count(isbn13) as with_isbn')
            ->selectRaw('sum(case when cover_url is not null or cover_path is not null then 1 else 0 end) as with_cover')
            ->selectRaw('sum(case when verified = 1 then 1 else 0 end) as verified')
            ->selectRaw('sum(case when created_at >= ? then 1 else 0 end) as added_recently', [
                now()->subDays(self::RECENT_DAYS),
            ])
            ->first();

        $total = (int) $books->total;
        $withIsbn = (int) $books->with_isbn;
        $withCover = (int) $books->with_cover;
        $verified = (int) $books->verified;

        return [
            'books' => $total,
            'with_isbn' => $withIsbn,
            'with_isbn_percent' => $this->percentOf($withIsbn, $total),
            'with_cover' => $withCover,
            'with_cover_percent' => $this->percentOf($withCover, $total),
            'verified' => $verified,
            'unverified' => $total - $verified,
            'added_recently' => (int) $books->added_recently,
            'recent_days' => self::RECENT_DAYS,
            'authors' => Author::count(),
            'publishers' => Publisher::count(),
        ];
    }

    /**
     * How many books each source knows about, busiest first.
     *
     * @return Collection<string, int>
     */
    public function booksPerSource(): Collection
    {
        return BookSource::query()
            ->selectRaw('source_key, count(*) as total')
            ->groupBy('source_key')
            ->orderByDesc('total')
            ->orderBy('source_key')
            ->pluck('total', 'source_key')
            ->map(fn (int|string $total): int => (int) $total);
    }

    private function percentOf(int $part, int $total): float
    {
        return $total === 0 ? 0.0 : round($part / $total * 100, 1);
    }
}
