<?php

declare(strict_types=1);

namespace App\Catalogue;

use App\Enums\TrustLevel;
use App\Models\Author;
use App\Models\Book;
use App\Models\BookSource;
use App\Models\Publisher;
use App\Scraping\DTO\RawBook;
use Illuminate\Support\Facades\DB;

use function App\Support\book_fingerprint;
use function App\Support\normalize_author_name;
use function App\Support\normalize_isbn;
use function App\Support\normalize_title;
use function App\Support\split_author_name;
use function App\Support\strip_title_noise;
use function App\Support\to_cyrillic;
use function App\Support\to_latin;

/**
 * Folds one scraped page into the canonical catalogue.
 *
 * The canonical record is never edited in place from a single source. Each
 * source keeps its own row with its raw payload, and the book is rebuilt from
 * all of them, highest trust first. That way a later scrape from a publisher
 * cannot be undone by an earlier one from a shop, and re-running the
 * normalization layer over stored payloads fixes history without any network.
 */
final readonly class UpsertBookFromSource
{
    /**
     * @return array{book: Book, created: bool}
     */
    public function handle(RawBook $raw, TrustLevel $trust): array
    {
        return DB::transaction(function () use ($raw, $trust): array {
            $book = $this->resolveBook($raw);
            $created = $book->wasRecentlyCreated;

            $this->recordSource($book, $raw);
            $this->rebuild($book->fresh(['sources']) ?? $book, $trust);

            return ['book' => $book->refresh(), 'created' => $created];
        });
    }

    /**
     * A valid ISBN-13 is the merge key. Without one, the fingerprint of the
     * normalized title, first author and year stands in.
     */
    private function resolveBook(RawBook $raw): Book
    {
        $isbn13 = normalize_isbn($raw->isbn);
        $title = strip_title_noise((string) $raw->title);

        if ($isbn13 !== null) {
            return Book::firstOrCreate(
                ['isbn13' => $isbn13],
                ['title' => $title, 'title_normalized' => normalize_title($title)],
            );
        }

        $fingerprint = book_fingerprint(
            $title,
            $raw->authors[0] ?? null,
            self::year($raw->publishedYear),
        );

        return Book::firstOrCreate(
            ['fingerprint' => $fingerprint],
            ['title' => $title, 'title_normalized' => normalize_title($title)],
        );
    }

    private function recordSource(Book $book, RawBook $raw): void
    {
        BookSource::updateOrCreate(
            [
                'source_key' => $raw->sourceKey,
                'external_id' => $raw->externalId ?? $raw->url,
            ],
            [
                'book_id' => $book->id,
                'url' => $raw->url,
                'raw_payload' => $raw->toArray(),
                'price' => self::decimal($raw->price),
                'in_stock' => $raw->inStock,
                'scraped_at' => now(),
            ],
        );
    }

    /**
     * Rebuild every canonical field from the sources, most trusted first.
     *
     * Fields a human locked are left exactly as they are.
     */
    private function rebuild(Book $book, TrustLevel $fallbackTrust): void
    {
        $sources = $book->sources
            ->sortByDesc(fn (BookSource $source): int => $source->trustLevel()->value)
            ->values();

        /** @var array<int, RawBook> $records */
        $records = $sources
            ->map(fn (BookSource $source): RawBook => RawBook::fromArray($source->raw_payload ?? []))
            ->all();

        $title = strip_title_noise((string) $this->firstValue($records, fn (RawBook $r) => $r->title));
        $authorNames = $this->firstValue($records, fn (RawBook $r) => $r->authors === [] ? null : $r->authors) ?? [];
        $publisherName = $this->firstValue($records, fn (RawBook $r) => $r->publisher);

        $attributes = array_filter([
            'title' => $title === '' ? null : $title,
            'title_latin' => $title === '' ? null : to_latin($title),
            'title_cyrillic' => $title === '' ? null : to_cyrillic($title),
            'title_normalized' => $title === '' ? null : normalize_title($title),
            'subtitle' => $this->firstValue($records, fn (RawBook $r) => $r->subtitle),
            'published_year' => self::year($this->firstValue($records, fn (RawBook $r) => $r->publishedYear)),
            'pages' => self::digits($this->firstValue($records, fn (RawBook $r) => $r->pages)),
            'language' => $this->firstValue($records, fn (RawBook $r) => $r->language),
            'description' => $this->firstValue($records, fn (RawBook $r) => $r->description),
            'cover_url' => $this->firstValue($records, fn (RawBook $r) => $r->coverUrl),
            'isbn13' => normalize_isbn($this->firstValue($records, fn (RawBook $r) => $r->isbn)),
            'publisher_id' => $publisherName === null ? null : $this->publisherFor($publisherName)->id,
        ], static fn (mixed $value): bool => $value !== null);

        $locked = $book->locked_fields ?? [];

        $book->fill(array_diff_key($attributes, array_flip($locked)))->save();

        if ($authorNames !== [] && ! in_array('authors', $locked, true)) {
            $this->syncAuthors($book, $authorNames);
        }
    }

    /**
     * The first non-null value any source offers, in trust order.
     *
     * @param  array<int, RawBook>  $records
     */
    private function firstValue(array $records, callable $pick): mixed
    {
        foreach ($records as $record) {
            $value = $pick($record);

            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function publisherFor(string $name): Publisher
    {
        $latin = to_latin($name);

        return Publisher::firstOrCreate(
            ['name_normalized' => normalize_title($name)],
            [
                'name' => $name,
                'name_latin' => $latin,
                'name_cyrillic' => to_cyrillic($name),
            ],
        );
    }

    /**
     * @param  array<int, string>  $names
     */
    private function syncAuthors(Book $book, array $names): void
    {
        $pivot = [];

        foreach (array_values($names) as $position => $name) {
            $parts = split_author_name($name);
            $latin = to_latin($name);

            $author = Author::firstOrCreate(
                ['full_name_normalized' => normalize_author_name($name)],
                [
                    'full_name' => $name,
                    'full_name_latin' => $latin,
                    'full_name_cyrillic' => to_cyrillic($name),
                    'given_name' => $parts['given'],
                    'family_name' => $parts['family'],
                ],
            );

            $pivot[$author->id] = ['position' => $position];
        }

        $book->authors()->sync($pivot);
    }

    /**
     * Sites write the year as "2021", "2021-yil" or inside a sentence.
     */
    private static function year(?string $value): ?int
    {
        if ($value === null || preg_match('/(1[5-9]\d{2}|20\d{2})/', $value, $match) !== 1) {
            return null;
        }

        return (int) $match[1];
    }

    /**
     * "336", "336 bet" and "336 стр." all mean 336.
     */
    private static function digits(?string $value): ?int
    {
        if ($value === null || preg_match('/(\d+)/', $value, $match) !== 1) {
            return null;
        }

        return (int) $match[1];
    }

    private static function decimal(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $clean = preg_replace('/[^\d.]/', '', $value);

        return $clean === '' || $clean === null ? null : $clean;
    }
}
