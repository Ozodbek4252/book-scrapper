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

    private const BASE_URL = 'https://asaxiy.uz';

    private const SITEMAP_CATEGORIES = 'https://asaxiy.uz/sitemap-categories.xml';

    /** Books live under this category branch. */
    private const BOOK_CATEGORY_PREFIX = '/product/knigi';

    /** Product tiles link to /product/{slug} with no second path segment. */
    private const PRODUCT_LINK_PATTERN = '#href="(/product/[a-z0-9][a-z0-9-]{7,})"#i';

    /**
     * A safety stop in case their pagination never terminates. The whole book
     * catalogue is around 275 pages, so this is well clear of a real walk.
     */
    private const MAX_CATEGORY_PAGES = 2000;

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
     * Walk the book categories, not the sitemap.
     *
     * The sitemap lists all ~50,000 goods with nothing in a URL to say which
     * are books, so crawling it means fetching watch straps to find novels.
     * The category pages under /product/knigi are server rendered and paginated,
     * and their robots.txt allows both. The category sitemap is used only to
     * tell a product link apart from a category link, since both are
     * /product/{slug}.
     *
     * @return Generator<int, string>
     */
    public function discover(): Generator
    {
        $categories = $this->categoryPaths();
        $bookCategories = $this->bookCategories($categories);
        $categoryLookup = array_flip($categories);
        $seen = [];

        foreach ($bookCategories as $category) {
            for ($page = 1; $page <= self::MAX_CATEGORY_PAGES; $page++) {
                $url = self::BASE_URL.$category.($page > 1 ? '?page='.$page : '');
                $html = $this->fetcher->get($url)->body;

                $fresh = [];

                foreach ($this->productPaths($html) as $path) {
                    if (isset($categoryLookup[$path]) || isset($seen[$path])) {
                        continue;
                    }

                    $seen[$path] = true;
                    $fresh[] = $path;
                }

                // A page that adds nothing means the category is exhausted, or
                // their pagination is ignoring us. Either way, move on.
                if ($fresh === []) {
                    break;
                }

                foreach ($fresh as $path) {
                    yield self::BASE_URL.$path;
                }
            }
        }
    }

    /**
     * The smallest set of categories that still covers every book.
     *
     * /product/knigi lists the whole book catalogue, and the other book
     * categories are subsets of it. Walking only the parent halves the number
     * of category pages requested and finds exactly the same products.
     *
     * @param  array<int, string>  $categories
     * @return array<int, string>
     */
    private function bookCategories(array $categories): array
    {
        $books = array_values(array_filter(
            $categories,
            static fn (string $path): bool => str_starts_with($path, self::BOOK_CATEGORY_PREFIX),
        ));

        return in_array(self::BOOK_CATEGORY_PREFIX, $books, true)
            ? [self::BOOK_CATEGORY_PREFIX]
            : $books;
    }

    /**
     * Every category path the site publishes, so a category link is never
     * mistaken for a product.
     *
     * @return array<int, string>
     */
    private function categoryPaths(): array
    {
        $xml = $this->fetcher->get(self::SITEMAP_CATEGORIES)->body;

        $paths = [];

        foreach ($this->locations($xml) as $url) {
            $path = parse_url($url, PHP_URL_PATH);

            // The site publishes a /ru/ mirror of every page.
            if (is_string($path) && ! str_starts_with($path, '/ru/')) {
                $paths[] = rtrim($path, '/');
            }
        }

        return array_values(array_unique($paths));
    }

    /**
     * @return array<int, string>
     */
    private function productPaths(string $html): array
    {
        preg_match_all(self::PRODUCT_LINK_PATTERN, $html, $matches);

        return array_values(array_unique($matches[1]));
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
        $xpath = Html::xpath($html);
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

            $label = Html::text($cells->item(0)?->textContent ?? '');
            $value = Html::text($cells->item(1)?->textContent ?? '');

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

        $title = Html::text($node->textContent);

        return $title === '' ? null : $title;
    }
}
