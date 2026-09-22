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
 * hilolnashr.uz, a publisher's storefront that also carries ~180 other
 * imprints, each with its own manufacturer-branded page.
 *
 * There is no single catalogue-wide listing and no usable sitemap, so
 * discovery walks every manufacturer page instead of one parent category.
 * A product page and a manufacturer page share the same flat /{slug} shape,
 * so a link cannot be told apart from a manufacturer link by its URL alone;
 * known manufacturer paths are excluded up front, and parse() is the final
 * word regardless, since a manufacturer's own page is itself always one of
 * the links it lists (their "you may also like" cross-links).
 *
 * A product page's schema.org block is typed Product, not Book, so it has
 * no author field — schema.org only puts that on a CreativeWork. Nothing
 * else on the page carries it either. This source never has authors.
 */
final readonly class HilolNashrDriver implements SourceDriver
{
    public const KEY = 'hilolnashr_uz';

    private const BASE_URL = 'https://hilolnashr.uz';

    private const MANUFACTURER_LIST_URL = 'https://hilolnashr.uz/index.php?route=product/manufacturer';

    private const LINK_PATTERN = '#href="https://hilolnashr\.uz/([a-z0-9][a-z0-9-]{2,})"#i';

    /** A safety stop; no single manufacturer runs anywhere near this deep. */
    private const MAX_MANUFACTURER_PAGES = 200;

    private const XPATH_JSON_LD = '//script[@type="application/ld+json"]';

    private const XPATH_ISBN = '//li[@class="product-isbn"]/span';

    private const XPATH_MODEL = '//li[@class="product-model"]/span';

    /** The one place publisher, year, pages and cover type are printed. */
    private const XPATH_DESCRIPTION = '//div[@class="block-content expand-content"]';

    /**
     * Labels as the site prints them, in Uzbek Cyrillic. The colon sits
     * inside the closing </b> on some pages and outside it on others, so the
     * pattern this builds allows either.
     */
    private const LABEL_PUBLISHER = 'Нашриёт';

    private const LABEL_YEAR = 'Сана';

    private const LABEL_PAGES = 'Ҳажми';

    private const LABEL_COVER = 'Муқоваси';

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
        $manufacturers = $this->manufacturerPaths();
        $manufacturerLookup = array_flip($manufacturers);
        $seen = [];

        foreach ($manufacturers as $manufacturer) {
            for ($page = 1; $page <= self::MAX_MANUFACTURER_PAGES; $page++) {
                $url = self::BASE_URL.'/'.$manufacturer.($page > 1 ? '?page='.$page : '');
                $html = $this->fetcher->get($url)->body;

                $fresh = [];

                foreach ($this->linkedSlugs($html) as $slug) {
                    if (isset($manufacturerLookup[$slug]) || isset($seen[$slug])) {
                        continue;
                    }

                    $seen[$slug] = true;
                    $fresh[] = $slug;
                }

                // A page that adds nothing means this manufacturer is
                // exhausted, or their pagination is ignoring us either way.
                if ($fresh === []) {
                    break;
                }

                foreach ($fresh as $slug) {
                    yield self::BASE_URL.'/'.$slug;
                }
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

        $isbn = $this->text($xpath, self::XPATH_ISBN);

        // A manufacturer's own storefront sells more than books; the ISBN is
        // the one field nothing else on the site carries.
        if ($isbn === null) {
            return null;
        }

        $description = $this->descriptionHtml($xpath);
        $offer = is_array($product['offers'] ?? null) ? $product['offers'] : [];
        $brand = is_array($product['brand'] ?? null) ? $product['brand'] : [];

        return new RawBook(
            sourceKey: self::KEY,
            url: $url,
            externalId: $this->text($xpath, self::XPATH_MODEL),
            title: isset($product['name']) ? (string) $product['name'] : null,
            authors: [],
            publisher: $brand['name'] ?? $this->label($description, self::LABEL_PUBLISHER),
            isbn: $isbn,
            publishedYear: self::firstYear($this->label($description, self::LABEL_YEAR)),
            pages: self::firstNumber($this->label($description, self::LABEL_PAGES)),
            description: isset($product['description']) ? (string) $product['description'] : null,
            coverUrl: isset($product['image']) ? (string) $product['image'] : null,
            price: isset($offer['price']) ? (string) $offer['price'] : null,
            inStock: isset($offer['availability'])
                ? str_contains((string) $offer['availability'], 'InStock')
                : null,
            payload: [
                'description' => $description,
                'json_ld' => $product,
                'cover_type' => $this->label($description, self::LABEL_COVER),
            ],
        );
    }

    /**
     * Every manufacturer this site lists, so a manufacturer's own link is
     * never mistaken for a product.
     *
     * @return array<int, string>
     */
    private function manufacturerPaths(): array
    {
        $html = $this->fetcher->get(self::MANUFACTURER_LIST_URL)->body;

        return $this->linkedSlugs($html);
    }

    /**
     * @return array<int, string>
     */
    private function linkedSlugs(string $html): array
    {
        preg_match_all(self::LINK_PATTERN, $html, $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * "624 бет" -> "624". Nothing here should ever need a second number.
     */
    private static function firstNumber(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return preg_match('/\d+/u', $value, $match) === 1 ? $match[0] : null;
    }

    /**
     * "2025 йил" -> "2025".
     */
    private static function firstYear(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return preg_match('/\d{4}/u', $value, $match) === 1 ? $match[0] : null;
    }

    /**
     * One value out of the free text block, keyed on the bold label the
     * site prints before it. The colon lands inside or outside the </b>
     * depending on the page, so both are matched, and the value runs to the
     * next tag rather than a fixed length.
     */
    private function label(string $descriptionHtml, string $label): ?string
    {
        $pattern = '/<b>\s*'.preg_quote($label, '/').'\s*:?\s*(?:&nbsp;)?\s*<\/b>\s*(?:&nbsp;)?\s*([^<]+)/u';

        if (preg_match($pattern, $descriptionHtml, $match) !== 1) {
            return null;
        }

        $value = Html::text(str_replace('&nbsp;', ' ', $match[1]));

        return $value === '' ? null : $value;
    }

    /**
     * The description block's own markup, kept as HTML rather than text
     * because label() matches against its <b> tags.
     */
    private function descriptionHtml(DOMXPath $xpath): string
    {
        $node = $xpath->query(self::XPATH_DESCRIPTION)?->item(0);

        if ($node === null) {
            return '';
        }

        return (string) $node->ownerDocument?->saveHTML($node);
    }

    private function text(DOMXPath $xpath, string $query): ?string
    {
        $node = $xpath->query($query)?->item(0);

        if ($node === null) {
            return null;
        }

        $text = Html::text($node->textContent);

        return $text === '' ? null : $text;
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
