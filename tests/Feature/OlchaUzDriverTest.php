<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Scraping\Drivers\OlchaUzDriver;
use App\Scraping\DTO\RawBook;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class OlchaUzDriverTest extends TestCase
{
    private const URL = 'https://olcha.uz/oz/product/view/saodat-asri-qissalari-1-2-zild-amad-lutfii-qozonci';

    private function driver(): OlchaUzDriver
    {
        return app(OlchaUzDriver::class);
    }

    private function book(): RawBook
    {
        Http::preventStrayRequests();

        $book = $this->driver()->parse(
            self::URL,
            (string) file_get_contents(base_path('tests/Fixtures/olcha_uz/product.html')),
        );

        $this->assertNotNull($book, 'the fixture should parse as a book');

        return $book;
    }

    public function test_it_reads_a_record_off_a_saved_page(): void
    {
        $book = $this->book();

        $this->assertSame('olcha_uz', $book->sourceKey);
        $this->assertSame('591195', $book->externalId);
        $this->assertStringContainsString('Saodat asri qissalari', (string) $book->title);
        $this->assertSame('Axmad Lutfiy Kozonchi', $book->authors[0]);
        $this->assertSame('Munir nashriyoti', $book->publisher);
        $this->assertSame('270000', $book->price);
        $this->assertTrue($book->inStock);
    }

    public function test_it_never_claims_an_isbn_because_the_site_has_none(): void
    {
        $this->assertNull($this->book()->isbn);
    }

    public function test_a_volume_title_is_not_mistaken_for_a_page_count(): void
    {
        $book = $this->book();

        // Their "Bet" row holds a volume title on multi volume sets. Turning
        // that into a number would say this book has one page.
        $this->assertNull($book->pages);
        $this->assertStringContainsString('1-qism', $book->payload['params']['Bet']);
    }

    public function test_a_real_page_count_is_kept(): void
    {
        $html = '<html><body>'
            .'<script type="application/ld+json">{"@type":"Product","name":"Kitob","sku":"1"}</script>'
            .'<div class="params__row"><div class="params__col"><span>Bet</span></div>'
            .'<div class="params__col"><span>336 bet</span></div></div>'
            .'</body></html>';

        $this->assertSame('336 bet', $this->driver()->parse(self::URL, $html)?->pages);
    }

    public function test_a_page_with_no_product_data_is_skipped(): void
    {
        $this->assertNull($this->driver()->parse(self::URL, '<html><body><h1>Nothing</h1></body></html>'));
        $this->assertNull($this->driver()->parse(self::URL, ''));
    }

    public function test_it_keeps_the_raw_values_for_re_parsing(): void
    {
        $payload = $this->book()->payload;

        $this->assertSame('Qattiq qog‘oz', $payload['cover_type']);
        $this->assertSame('Product', $payload['json_ld']['@type']);
        $this->assertArrayHasKey('Muallif', $payload['params']);
    }

    public function test_discovery_walks_the_category_and_stops_when_a_page_repeats(): void
    {
        Storage::fake('local');
        Cache::clear();
        Sleep::fake();

        $page1 = '<a href="/oz/product/view/jorj-oruell-1984">a</a><a href="/oz/product/view/atom-odatlar">b</a>';
        $page2 = '<a href="/oz/product/view/tom-soyer">c</a>';

        Http::fake([
            '*/robots.txt' => Http::response("User-agent: *\nDisallow: /api/"),
            'https://olcha.uz/oz/category/knigi' => Http::response($page1),
            'https://olcha.uz/oz/category/knigi?page=2' => Http::response($page2),
            'https://olcha.uz/oz/category/knigi?page=3' => Http::response($page2),
        ]);

        $urls = iterator_to_array($this->driver()->discover());

        $this->assertSame([
            'https://olcha.uz/oz/product/view/jorj-oruell-1984',
            'https://olcha.uz/oz/product/view/atom-odatlar',
            'https://olcha.uz/oz/product/view/tom-soyer',
        ], $urls);

        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'page=4'));
    }

    public function test_discovery_stays_on_one_locale(): void
    {
        Storage::fake('local');
        Cache::clear();
        Sleep::fake();

        Http::fake([
            '*/robots.txt' => Http::response("User-agent: *\nDisallow: /api/"),
            'https://olcha.uz/oz/category/knigi' => Http::response('<a href="/oz/product/view/kitob">a</a>'),
            'https://olcha.uz/oz/category/knigi?page=2' => Http::response(''),
        ]);

        iterator_to_array($this->driver()->discover());

        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/ru/') || str_contains($request->url(), '/uz/'));
    }

    public function test_it_reports_its_key(): void
    {
        $this->assertSame('olcha_uz', $this->driver()->key());
    }
}
