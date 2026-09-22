<?php

declare(strict_types=1);

namespace App\Scraping\Drivers;

use App\Scraping\Contracts\SourceDriver;
use App\Scraping\DTO\RawBook;
use App\Scraping\Html;
use App\Scraping\Http\Fetcher;
use DOMXPath;
use Generator;
use Throwable;

/**
 * qamar.uz, a bookstore whose sitemap already lists every book on its own.
 *
 * Discovery reads the sitemap directly instead of walking category pages: it
 * already lists every /kitob/{slug} URL, so there is nothing to paginate.
 *
 * A product page carries a schema.org Book block for title, author,
 * publisher, pages, cover, price and stock. The one field it leaves out,
 * ISBN, comes from the product details table instead.
 */
final readonly class QamarUzDriver implements SourceDriver
{
    public const KEY = 'qamar_uz';

    private const SITEMAP = 'https://qamar.uz/sitemap.xml';

    private const BOOK_PATH_PREFIX = '/kitob/';

    private const XPATH_JSON_LD = '//script[@type="application/ld+json"]';

    /** Their whole page holds exactly one table: product details. */
    private const XPATH_DETAIL_ROWS = '//tr';

    private const LABEL_ISBN = 'ISBN / shtrix-kod';

    private const LABEL_COVER = 'Muqova turi';

    public function __construct(private Fetcher $fetcher) {}

    public function key(): string
    {
        return self::KEY;
    }

    /**
     * @return Generator<int, string>
     */
    public function discover(): Generator
    {
        $xml = $this->fetcher->get(self::SITEMAP)->body;

        foreach ($this->locations($xml) as $url) {
            $path = parse_url($url, PHP_URL_PATH);

            if (is_string($path) && str_starts_with($path, self::BOOK_PATH_PREFIX)) {
                yield $url;
            }
        }
    }

    public function fetch(string $url): ?RawBook
    {
        return $this->parse($url, $this->fetcher->get($url)->body);
    }

    /**
     * Read one saved page, so parser tests need no network.
     */
    public function parse(string $url, string $html): ?RawBook
    {
        $xpath = Html::xpath($html);
        $book = $this->bookJsonLd($xpath);

        if ($book === []) {
            return null;
        }

        $details = $this->details($xpath);
        $offer = is_array($book['offers'] ?? null) ? $book['offers'] : [];
        $author = is_array($book['author'] ?? null) ? $book['author'] : [];
        $publisher = is_array($book['publisher'] ?? null) ? $book['publisher'] : [];

        return new RawBook(
            sourceKey: self::KEY,
            url: $url,
            externalId: self::slug($url),
            title: isset($book['name']) ? (string) $book['name'] : null,
            authors: isset($author['name']) ? [(string) $author['name']] : [],
            publisher: isset($publisher['name']) ? (string) $publisher['name'] : null,
            isbn: $details[self::LABEL_ISBN] ?? null,
            pages: isset($book['numberOfPages']) ? (string) $book['numberOfPages'] : null,
            coverUrl: isset($book['image']) ? (string) $book['image'] : null,
            price: isset($offer['price']) ? (string) $offer['price'] : null,
            inStock: isset($offer['availability'])
                ? str_contains((string) $offer['availability'], 'InStock')
                : null,
            payload: [
                'details' => $details,
                'json_ld' => $book,
                'cover_type' => $details[self::LABEL_COVER] ?? null,
            ],
        );
    }

    /**
     * The slug is the only stable identifier a product page carries; nothing
     * in their markup exposes a separate numeric id.
     */
    private static function slug(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path)) {
            return null;
        }

        $slug = basename(rtrim($path, '/'));

        return $slug === '' ? null : $slug;
    }

    /**
     * <loc> entries from a sitemap.
     *
     * @return array<int, string>
     */
    private function locations(string $xml): array
    {
        preg_match_all('#<loc>\s*([^<\s]+)\s*</loc>#i', $xml, $matches);

        return $matches[1];
    }

    /**
     * The product details table, keyed by the label the site prints.
     *
     * @return array<string, string>
     */
    private function details(DOMXPath $xpath): array
    {
        $rows = [];

        foreach ($xpath->query(self::XPATH_DETAIL_ROWS) ?: [] as $row) {
            $cells = $xpath->query('.//td', $row);

            if ($cells === false || $cells->length < 2) {
                continue;
            }

            $label = Html::text($cells->item(0)?->textContent ?? '');
            $value = Html::text($cells->item(1)?->textContent ?? '');

            if ($label !== '' && $value !== '') {
                $rows[$label] = $value;
            }
        }

        return $rows;
    }

    /**
     * The schema.org Book block, which carries most of a page's data.
     *
     * @return array<string, mixed>
     */
    private function bookJsonLd(DOMXPath $xpath): array
    {
        foreach ($xpath->query(self::XPATH_JSON_LD) ?: [] as $script) {
            try {
                $decoded = json_decode(trim($script->textContent), true, flags: JSON_THROW_ON_ERROR);
            } catch (Throwable) {
                continue;
            }

            foreach (is_array($decoded) && array_is_list($decoded) ? $decoded : [$decoded] as $item) {
                if (is_array($item) && ($item['@type'] ?? null) === 'Book') {
                    return $item;
                }
            }
        }

        return [];
    }
}
