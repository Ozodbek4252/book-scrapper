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
 * olcha.uz, a general marketplace with a large book section.
 *
 * It publishes no ISBNs at all, so nothing from here can answer a barcode
 * scan on its own. It earns its place by enriching books already known from a
 * source that does have ISBNs, and by covering titles that source misses. The
 * merge attaches these records by fingerprint.
 *
 * Their robots.txt forbids /api/ and /search, so discovery walks the server
 * rendered category pages, which is the only permitted route to a product list.
 */
final readonly class OlchaUzDriver implements SourceDriver
{
    public const KEY = 'olcha_uz';

    private const BASE_URL = 'https://olcha.uz';

    /** One locale only; the site mirrors every page at /uz/ and /ru/ too. */
    private const BOOK_CATEGORY = '/oz/category/knigi';

    private const PRODUCT_LINK_PATTERN = '#/oz/product/view/([a-z0-9][a-z0-9-]{5,})#i';

    /** A safety stop; the book category runs to roughly 1,500 pages. */
    private const MAX_CATEGORY_PAGES = 3000;

    private const XPATH_JSON_LD = '//script[@type="application/ld+json"]';

    /** Their attribute table: two params__col cells per params__row. */
    private const XPATH_PARAM_ROWS = '//*[contains(@class, "params__row")]';

    private const LABEL_AUTHOR = 'Muallif';

    private const LABEL_PAGES = 'Bet';

    private const LABEL_COVER = 'Muqova turi';

    private const LABEL_LANGUAGE = 'Til';

    private const LABEL_YEAR = 'Yil';

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
        $seen = [];

        for ($page = 1; $page <= self::MAX_CATEGORY_PAGES; $page++) {
            $url = self::BASE_URL.self::BOOK_CATEGORY.($page > 1 ? '?page='.$page : '');
            $html = $this->fetcher->get($url)->body;

            $fresh = [];

            foreach ($this->productSlugs($html) as $slug) {
                if (isset($seen[$slug])) {
                    continue;
                }

                $seen[$slug] = true;
                $fresh[] = $slug;
            }

            if ($fresh === []) {
                break;
            }

            foreach ($fresh as $slug) {
                yield self::BASE_URL.'/oz/product/view/'.$slug;
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
        $product = $this->productJsonLd($xpath);

        if ($product === []) {
            return null;
        }

        $params = $this->params($xpath);
        $offer = is_array($product['offers'] ?? null) ? $product['offers'] : [];

        return new RawBook(
            sourceKey: self::KEY,
            url: $url,
            externalId: isset($product['sku']) ? (string) $product['sku'] : null,
            title: isset($product['name']) ? (string) $product['name'] : null,
            authors: isset($params[self::LABEL_AUTHOR]) ? [$params[self::LABEL_AUTHOR]] : [],
            // The publisher is the marketplace's brand, not an attribute row.
            publisher: is_array($product['brand'] ?? null) ? ($product['brand']['name'] ?? null) : null,
            isbn: null,
            publishedYear: $params[self::LABEL_YEAR] ?? null,
            pages: self::pageCount($params[self::LABEL_PAGES] ?? null),
            language: $params[self::LABEL_LANGUAGE] ?? null,
            coverUrl: self::firstImage($product['image'] ?? null),
            price: isset($offer['price']) ? (string) $offer['price'] : null,
            inStock: isset($offer['availability'])
                ? str_contains((string) $offer['availability'], 'InStock')
                : null,
            payload: [
                'params' => $params,
                'json_ld' => $product,
                'cover_type' => $params[self::LABEL_COVER] ?? null,
            ],
        );
    }

    /**
     * Their "Bet" row usually holds a page count, but on multi volume sets it
     * holds a volume title instead. Anything that is not a count is left out
     * rather than turned into a wrong number; the raw value stays in payload.
     */
    private static function pageCount(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return preg_match('/^\s*\d[\d\s]*\s*(bet|б|стр\.?)?\s*$/iu', $value) === 1 ? $value : null;
    }

    private static function firstImage(mixed $image): ?string
    {
        if (is_string($image)) {
            return $image;
        }

        return is_array($image) && isset($image[0]) && is_string($image[0]) ? $image[0] : null;
    }

    /**
     * @return array<int, string>
     */
    private function productSlugs(string $html): array
    {
        preg_match_all(self::PRODUCT_LINK_PATTERN, $html, $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * @return array<string, string>
     */
    private function params(DOMXPath $xpath): array
    {
        $rows = [];

        foreach ($xpath->query(self::XPATH_PARAM_ROWS) ?: [] as $row) {
            $cells = $xpath->query('.//*[contains(@class, "params__col")]', $row);

            if ($cells === false || $cells->length < 2) {
                continue;
            }

            $label = Html::text($cells->item(0)?->textContent ?? '');
            $value = Html::text($cells->item(1)?->textContent ?? '');

            // A repeated label on a multi volume set keeps the first value.
            if ($label !== '' && $value !== '' && ! isset($rows[$label])) {
                $rows[$label] = $value;
            }
        }

        return $rows;
    }

    /**
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
}
