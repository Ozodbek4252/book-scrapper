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

    private function fakeSite(): void
    {
        $categorySitemap = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
              <url><loc>https://asaxiy.uz/product/knigi/bestseller</loc></url>
              <url><loc>https://asaxiy.uz/ru/product/knigi/bestseller</loc></url>
              <url><loc>https://asaxiy.uz/product/bytovaya-tehnika</loc></url>
            </urlset>
            XML;

        // A category page links to products and to other categories alike.
        $page1 = '<a href="/product/bytovaya-tehnika">Maishiy</a>'
            .'<a href="/product/jorj-oruell-1984">1984</a>'
            .'<a href="/product/jeyms-klir-atom-odatlar">Atom odatlar</a>';

        $page2 = '<a href="/product/bytovaya-tehnika">Maishiy</a>'
            .'<a href="/product/mark-tven-tom-soyerning-sarguzashtlari">Tom Soyer</a>';

        // Page three repeats page two, which is how a category ends.
        Http::fake([
            '*/robots.txt' => Http::response("User-agent: *\nDisallow: /admin"),
            'https://asaxiy.uz/sitemap-categories.xml' => Http::response($categorySitemap),
            'https://asaxiy.uz/product/knigi/bestseller' => Http::response($page1),
            'https://asaxiy.uz/product/knigi/bestseller?page=2' => Http::response($page2),
            'https://asaxiy.uz/product/knigi/bestseller?page=3' => Http::response($page2),
        ]);
    }

    public function test_it_yields_book_products_from_the_book_categories(): void
    {
        $this->fakeSite();

        $urls = iterator_to_array(app(AsaxiyUzDriver::class)->discover());

        $this->assertSame([
            'https://asaxiy.uz/product/jorj-oruell-1984',
            'https://asaxiy.uz/product/jeyms-klir-atom-odatlar',
            'https://asaxiy.uz/product/mark-tven-tom-soyerning-sarguzashtlari',
        ], $urls);
    }

    public function test_it_does_not_mistake_a_category_link_for_a_product(): void
    {
        $this->fakeSite();

        $urls = iterator_to_array(app(AsaxiyUzDriver::class)->discover());

        $this->assertNotContains('https://asaxiy.uz/product/bytovaya-tehnika', $urls);
    }

    public function test_it_stops_paginating_when_a_page_adds_nothing_new(): void
    {
        $this->fakeSite();

        iterator_to_array(app(AsaxiyUzDriver::class)->discover());

        // Page three matched page two, so page four is never requested.
        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'page=4'));
    }

    public function test_it_ignores_the_russian_mirror_of_every_category(): void
    {
        $this->fakeSite();

        iterator_to_array(app(AsaxiyUzDriver::class)->discover());

        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/ru/'));
    }

    public function test_discovery_is_lazy_so_a_full_catalogue_never_sits_in_memory(): void
    {
        $this->fakeSite();

        $generator = app(AsaxiyUzDriver::class)->discover();

        $this->assertSame('https://asaxiy.uz/product/jorj-oruell-1984', $generator->current());
        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'page=2'));
    }
}
