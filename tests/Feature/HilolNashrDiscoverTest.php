<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Scraping\Drivers\HilolNashrDriver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class HilolNashrDiscoverTest extends TestCase
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
        // The manufacturer list links manufacturers to each other too, the
        // same way a product page does.
        $manufacturerList = '<a href="https://hilolnashr.uz/akadem-nashr">Akademnashr</a>'
            .'<a href="https://hilolnashr.uz/gafur-gulom-nashriyoti">G`afur G`ulom</a>';

        $akademnashrPage1 = '<a href="https://hilolnashr.uz/gafur-gulom-nashriyoti">G`afur G`ulom</a>'
            .'<a href="https://hilolnashr.uz/otkan-kunlar-romoni">Oʻtkan kunlar</a>'
            .'<a href="https://hilolnashr.uz/mehrobdan-chayon">Mehrobdan chayon</a>';

        // Page two repeats page one, which is how a manufacturer ends.
        $gafurGulomPage1 = '<a href="https://hilolnashr.uz/akadem-nashr">Akademnashr</a>'
            .'<a href="https://hilolnashr.uz/sarob-romoni">Sarob</a>';

        Http::fake([
            '*/robots.txt' => Http::response('Disallow: /*?sort='),
            'https://hilolnashr.uz/index.php?route=product/manufacturer' => Http::response($manufacturerList),
            'https://hilolnashr.uz/akadem-nashr' => Http::response($akademnashrPage1),
            'https://hilolnashr.uz/akadem-nashr?page=2' => Http::response($akademnashrPage1),
            'https://hilolnashr.uz/gafur-gulom-nashriyoti' => Http::response($gafurGulomPage1),
            'https://hilolnashr.uz/gafur-gulom-nashriyoti?page=2' => Http::response($gafurGulomPage1),
        ]);
    }

    public function test_it_yields_product_links_from_every_manufacturer(): void
    {
        $this->fakeSite();

        $urls = iterator_to_array(app(HilolNashrDriver::class)->discover());

        $this->assertSame([
            'https://hilolnashr.uz/otkan-kunlar-romoni',
            'https://hilolnashr.uz/mehrobdan-chayon',
            'https://hilolnashr.uz/sarob-romoni',
        ], $urls);
    }

    public function test_it_does_not_mistake_a_manufacturer_link_for_a_product(): void
    {
        $this->fakeSite();

        $urls = iterator_to_array(app(HilolNashrDriver::class)->discover());

        $this->assertNotContains('https://hilolnashr.uz/akadem-nashr', $urls);
        $this->assertNotContains('https://hilolnashr.uz/gafur-gulom-nashriyoti', $urls);
    }

    public function test_it_stops_paginating_a_manufacturer_once_a_page_adds_nothing_new(): void
    {
        $this->fakeSite();

        iterator_to_array(app(HilolNashrDriver::class)->discover());

        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'akadem-nashr?page=3'));
    }

    public function test_discovery_is_lazy_so_the_full_manufacturer_list_never_sits_in_memory(): void
    {
        $this->fakeSite();

        $generator = app(HilolNashrDriver::class)->discover();

        $this->assertSame('https://hilolnashr.uz/otkan-kunlar-romoni', $generator->current());
        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'gafur-gulom'));
    }
}
