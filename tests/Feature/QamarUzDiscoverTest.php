<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Scraping\Drivers\QamarUzDriver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class QamarUzDiscoverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Cache::clear();
        Sleep::fake();
    }

    private function fakeSite(): void
    {
        $sitemap = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
              <url><loc>https://qamar.uz/</loc></url>
              <url><loc>https://qamar.uz/katalog/ozbek-adabiyoti</loc></url>
              <url><loc>https://qamar.uz/kitob/otkan-kunlar-romoni</loc></url>
              <url><loc>https://qamar.uz/kitob/mehrobdan-chayon</loc></url>
            </urlset>
            XML;

        Http::fake([
            '*/robots.txt' => Http::response("User-agent: *\nDisallow: /admin"),
            'https://qamar.uz/sitemap.xml' => Http::response($sitemap),
        ]);
    }

    public function test_it_yields_only_book_urls_from_the_sitemap(): void
    {
        $this->fakeSite();

        $urls = iterator_to_array(app(QamarUzDriver::class)->discover());

        $this->assertSame([
            'https://qamar.uz/kitob/otkan-kunlar-romoni',
            'https://qamar.uz/kitob/mehrobdan-chayon',
        ], $urls);
    }

    public function test_it_does_not_yield_the_homepage_or_a_category_page(): void
    {
        $this->fakeSite();

        $urls = iterator_to_array(app(QamarUzDriver::class)->discover());

        $this->assertNotContains('https://qamar.uz/', $urls);
        $this->assertNotContains('https://qamar.uz/katalog/ozbek-adabiyoti', $urls);
    }

    public function test_discovery_needs_only_the_one_sitemap_request(): void
    {
        $this->fakeSite();

        iterator_to_array(app(QamarUzDriver::class)->discover());

        Http::assertSentCount(2); // robots.txt, then the sitemap itself.
    }
}
