<?php

declare(strict_types=1);

namespace App\Scraping\Drivers;

use App\Scraping\Contracts\SourceDriver;
use App\Scraping\DTO\RawBook;
use App\Scraping\Http\Fetcher;
use DOMDocument;
use DOMXPath;
use Generator;
use Throwable;

/**
 * asaxiy.uz, a general e-commerce site with a large book section.
 *
 * Two things are read from a product page. The schema.org JSON-LD block gives
 * price, stock and image and is a published standard, so it is the stable part.
 * The characteristics table gives ISBN, publisher, author and page count, and
 * is matched on the labels the site prints rather than on positions.
 *
 * Every selector and label this site needs is a constant below, so a change on
 * their side is a one-line fix here.
 */
final readonly class AsaxiyUzDriver implements SourceDriver
{
    public const KEY = 'asaxiy_uz';

    private const SITEMAP_INDEX = 'https://asaxiy.uz/sitemap.xml';

    /** Only the product sitemaps hold goods; the rest are articles and filters. */
    private const SITEMAP_PRODUCTS_PATTERN = '#/sitemap-goods\d+\.xml$#';

    private const XPATH_JSON_LD = '//script[@type="application/ld+json"]';

    private const XPATH_CHARACTERISTIC_ROWS = '//*[@id="characteristics-content"]//tr';

    private const XPATH_TITLE = '//h1';

    /**
     * Labels as the site prints them, in Uzbek.
     */
    private const LABEL_ISBN = 'ISBN';

    private const LABEL_PAGES = 'Betlar soni';

    private const LABEL_YEAR = 'Chop etilgan yili';

    private const LABEL_PUBLISHER = 'Nashriyot';

    private const LABEL_AUTHOR = 'Muallif';

    private const LABEL_LANGUAGE = 'Til';

    private const LABEL_COVER = 'Muqovasi';

    private const LABEL_SCRIPT = 'Yozuv';

    public function __construct(private Fetcher $fetcher) {}

    public function key(): string
    {
        return self::KEY;
    }

    /**
     * Walk the sitemap index rather than the category pages: it is one request
     * per 10,000 products and their robots.txt disallows the filtered lists.
     *
     * Everything they sell is yielded, not only books. fetch() returns null for
     * anything without book fields, which is cheaper than guessing from a URL.
     *
     * @return Generator<int, string>
     */
    public function discover(): Generator
    {
        $index = $this->fetcher->get(self::SITEMAP_INDEX);

        foreach ($this->locations($index->body) as $sitemap) {
            if (preg_match(self::SITEMAP_PRODUCTS_PATTERN, $sitemap) !== 1) {
                continue;
            }

            $goods = $this->fetcher->get($sitemap);

            foreach ($this->locations($goods->body) as $url) {
                // The site publishes /ru/product/... duplicates of every page.
                if (str_contains($url, '/product/') && ! str_contains($url, '/ru/')) {
                    yield $url;
                }
            }
        }
    }

    public function fetch(string $url): ?RawBook
    {
        $body = $this->fetcher->get($url)->body;

        return $this->parse($url, $body);
    }

    /**
     * Read one saved page. Kept separate from fetch() so the parser tests run
     * against fixtures with no network at all.
     */
    public function parse(string $url, string $html): ?RawBook
    {
        $xpath = self::xpathFor($html);
        $characteristics = $this->characteristics($xpath);

        // No ISBN and no author means this is not a book, just another product.
        if (! isset($characteristics[self::LABEL_ISBN]) && ! isset($characteristics[self::LABEL_AUTHOR])) {
            return null;
        }

        $product = $this->productJsonLd($xpath);
        $offer = is_array($product['offers'] ?? null) ? $product['offers'] : [];

        $title = $this->title($xpath) ?? ($product['name'] ?? null);
        $author = $characteristics[self::LABEL_AUTHOR] ?? null;

        return new RawBook(
            sourceKey: self::KEY,
            url: $url,
            externalId: isset($product['sku']) ? (string) $product['sku'] : null,
            title: $title,
            authors: $author === null ? [] : [$author],
            publisher: $characteristics[self::LABEL_PUBLISHER] ?? null,
            isbn: $characteristics[self::LABEL_ISBN] ?? null,
            publishedYear: $characteristics[self::LABEL_YEAR] ?? null,
            pages: $characteristics[self::LABEL_PAGES] ?? null,
            language: $characteristics[self::LABEL_LANGUAGE] ?? null,
            description: isset($product['description']) ? (string) $product['description'] : null,
            coverUrl: isset($product['image']) ? (string) $product['image'] : null,
            price: isset($offer['price']) ? (string) $offer['price'] : null,
            inStock: isset($offer['availability'])
                ? str_contains((string) $offer['availability'], 'InStock')
                : null,
            payload: [
                'characteristics' => $characteristics,
                'json_ld' => $product,
                'cover_type' => $characteristics[self::LABEL_COVER] ?? null,
                'script' => $characteristics[self::LABEL_SCRIPT] ?? null,
            ],
        );
    }

    /**
     * <loc> entries from a sitemap or sitemap index.
     *
     * @return array<int, string>
     */
    private function locations(string $xml): array
    {
        preg_match_all('#<loc>\s*([^<\s]+)\s*</loc>#i', $xml, $matches);

        return $matches[1];
    }

    /**
     * The characteristics table, keyed by the label the site prints.
     *
     * @return array<string, string>
     */
    private function characteristics(DOMXPath $xpath): array
    {
        $rows = [];

        foreach ($xpath->query(self::XPATH_CHARACTERISTIC_ROWS) ?: [] as $row) {
            $cells = $xpath->query('.//td', $row);

            if ($cells === false || $cells->length < 2) {
                continue;
            }

            $label = self::text($cells->item(0)?->textContent ?? '');
            $value = self::text($cells->item(1)?->textContent ?? '');

            if ($label !== '' && $value !== '') {
                $rows[$label] = $value;
            }
        }

        return $rows;
    }

    /**
     * The schema.org Product block, which carries price, stock and image.
     *
     * @return array<string, mixed>
     */
    private function productJsonLd(DOMXPath $xpath): array
    {
        foreach ($xpath->query(self::XPATH_JSON_LD) ?: [] as $script) {
            try {
                $decoded = json_decode(trim($script->textContent), true, flags: JSON_THROW_ON_ERROR);
            } catch (Throwable) {
                continue;
            }

            foreach (is_array($decoded) && array_is_list($decoded) ? $decoded : [$decoded] as $item) {
                if (is_array($item) && ($item['@type'] ?? null) === 'Product') {
                    return $item;
                }
            }
        }

        return [];
    }

    private function title(DOMXPath $xpath): ?string
    {
        $node = $xpath->query(self::XPATH_TITLE)?->item(0);

        if ($node === null) {
            return null;
        }

        $title = self::text($node->textContent);

        return $title === '' ? null : $title;
    }

    private static function xpathFor(string $html): DOMXPath
    {
        $document = new DOMDocument;

        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($document);
    }

    private static function text(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode($value)));
    }
}
