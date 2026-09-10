<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Scraping\Drivers\AsaxiyUzDriver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class AsaxiyUzDiscoverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Cache::clear();
        Sleep::fake();
    }

    private function fakeSitemaps(): void
    {
        Http::fake([
            '*/robots.txt' => Http::response("User-agent: *\nDisallow: /admin"),
            'https://asaxiy.uz/sitemap.xml' => Http::response(<<<'XML'
                <?xml version="1.0" encoding="UTF-8"?>
                <sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
                  <sitemap><loc>https://asaxiy.uz/sitemap-categories.xml</loc></sitemap>
                  <sitemap><loc>https://asaxiy.uz/sitemap-goods1.xml</loc></sitemap>
                  <sitemap><loc>https://asaxiy.uz/sitemap-goods2.xml</loc></sitemap>
                </sitemapindex>
                XML),
            'https://asaxiy.uz/sitemap-goods1.xml' => Http::response(<<<'XML'
                <?xml version="1.0" encoding="UTF-8"?>
                <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
                  <url><loc>https://asaxiy.uz/product/chulpon-kecha-va-kunduz</loc></url>
                  <url><loc>https://asaxiy.uz/ru/product/chulpon-kecha-va-kunduz</loc></url>
                  <url><loc>https://asaxiy.uz/uz/catalog/knigi</loc></url>
                </urlset>
                XML),
            'https://asaxiy.uz/sitemap-goods2.xml' => Http::response(<<<'XML'
                <?xml version="1.0" encoding="UTF-8"?>
                <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
                  <url><loc>https://asaxiy.uz/product/abdulla-qodiriy-otkan-kunlar</loc></url>
                </urlset>
                XML),
            'https://asaxiy.uz/sitemap-categories.xml' => Http::response('<urlset><url><loc>https://asaxiy.uz/uz/catalog/x</loc></url></urlset>'),
        ]);
    }

    public function test_it_yields_product_urls_from_the_goods_sitemaps(): void
    {
        $this->fakeSitemaps();

        $urls = iterator_to_array(app(AsaxiyUzDriver::class)->discover());

        $this->assertSame([
            'https://asaxiy.uz/product/chulpon-kecha-va-kunduz',
            'https://asaxiy.uz/product/abdulla-qodiriy-otkan-kunlar',
        ], $urls);
    }

    public function test_it_skips_the_russian_duplicate_of_every_page(): void
    {
        $this->fakeSitemaps();

        $urls = iterator_to_array(app(AsaxiyUzDriver::class)->discover());

        $this->assertNotContains('https://asaxiy.uz/ru/product/chulpon-kecha-va-kunduz', $urls);
    }

    public function test_it_never_opens_the_sitemaps_that_hold_no_goods(): void
    {
        $this->fakeSitemaps();

        iterator_to_array(app(AsaxiyUzDriver::class)->discover());

        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'sitemap-categories'));
    }

    public function test_discovery_is_lazy_so_a_full_catalogue_never_sits_in_memory(): void
    {
        $this->fakeSitemaps();

        $generator = app(AsaxiyUzDriver::class)->discover();

        // Taking one URL must not have pulled the second goods sitemap yet.
        $this->assertSame('https://asaxiy.uz/product/chulpon-kecha-va-kunduz', $generator->current());
        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'sitemap-goods2'));
    }
}
