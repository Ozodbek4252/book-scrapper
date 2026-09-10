<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Scraping\Exceptions\FetchFailedException;
use App\Scraping\Http\Fetcher;
use Carbon\CarbonInterval;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class FetcherThrottleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Cache::clear();
        Sleep::fake();
    }

    private function fetcher(): Fetcher
    {
        return app(Fetcher::class);
    }

    /**
     * @param  array<string, mixed>  $responses
     */
    private function fakeSite(array $responses, string $robots = "User-agent: *\nDisallow:"): void
    {
        Http::fake(['*/robots.txt' => Http::response($robots)] + $responses);
    }

    public function test_the_first_request_to_a_domain_does_not_wait(): void
    {
        $this->fakeSite(['https://kitob.uz/a' => Http::response('a')]);

        $this->fetcher()->get('https://kitob.uz/a');

        Sleep::assertNeverSlept();
    }

    public function test_it_waits_a_second_between_requests_to_one_domain(): void
    {
        config(['scraping.rate_limit' => 1]);
        $this->fakeSite([
            'https://kitob.uz/a' => Http::response('a'),
            'https://kitob.uz/b' => Http::response('b'),
        ]);

        $this->fetcher()->get('https://kitob.uz/a');
        $this->fetcher()->get('https://kitob.uz/b');

        Sleep::assertSlept(fn (CarbonInterval $duration): bool => $duration->totalMilliseconds > 900);
    }

    public function test_a_higher_rate_limit_shortens_the_wait(): void
    {
        config(['scraping.rate_limit' => 10]);
        $this->fakeSite([
            'https://kitob.uz/a' => Http::response('a'),
            'https://kitob.uz/b' => Http::response('b'),
        ]);

        $this->fetcher()->get('https://kitob.uz/a');
        $this->fetcher()->get('https://kitob.uz/b');

        Sleep::assertSlept(fn (CarbonInterval $duration): bool => $duration->totalMilliseconds <= 100);
    }

    public function test_two_domains_do_not_wait_on_each_other(): void
    {
        config(['scraping.rate_limit' => 1]);
        $this->fakeSite([
            'https://kitob.uz/a' => Http::response('a'),
            'https://asaxiy.uz/a' => Http::response('a'),
        ]);

        $this->fetcher()->get('https://kitob.uz/a');
        $this->fetcher()->get('https://asaxiy.uz/a');

        Sleep::assertNeverSlept();
    }

    public function test_a_cached_page_costs_no_wait_at_all(): void
    {
        config(['scraping.rate_limit' => 1]);
        $this->fakeSite(['https://kitob.uz/a' => Http::response('a')]);

        $this->fetcher()->get('https://kitob.uz/a');
        $this->fetcher()->get('https://kitob.uz/a');
        $this->fetcher()->get('https://kitob.uz/a');

        Sleep::assertNeverSlept();
    }

    public function test_a_crawl_delay_in_robots_wins_when_it_asks_for_more_room(): void
    {
        config(['scraping.rate_limit' => 10]);
        $this->fakeSite(
            [
                'https://kitob.uz/a' => Http::response('a'),
                'https://kitob.uz/b' => Http::response('b'),
            ],
            "User-agent: *\nCrawl-delay: 5\nDisallow:",
        );

        $this->fetcher()->get('https://kitob.uz/a');
        $this->fetcher()->get('https://kitob.uz/b');

        Sleep::assertSlept(fn (CarbonInterval $duration): bool => $duration->totalMilliseconds > 4000);
    }

    public function test_our_own_rate_limit_wins_when_it_is_the_slower_one(): void
    {
        config(['scraping.rate_limit' => 1]);
        $this->fakeSite(
            [
                'https://kitob.uz/a' => Http::response('a'),
                'https://kitob.uz/b' => Http::response('b'),
            ],
            "User-agent: *\nCrawl-delay: 0.1\nDisallow:",
        );

        $this->fetcher()->get('https://kitob.uz/a');
        $this->fetcher()->get('https://kitob.uz/b');

        Sleep::assertSlept(fn (CarbonInterval $duration): bool => $duration->totalMilliseconds > 900);
    }

    public function test_one_domain_is_never_fetched_by_two_workers_at_once(): void
    {
        config(['scraping.lock_wait' => 1]);
        $this->fakeSite(['https://kitob.uz/a' => Http::response('a')]);

        // Stand in for another worker already crawling this domain.
        $heldByAnotherWorker = Cache::lock('scraping:domain:kitob.uz', 10);
        $this->assertTrue($heldByAnotherWorker->get());

        try {
            $this->expectException(FetchFailedException::class);
            $this->expectExceptionMessage('another worker held [kitob.uz]');

            $this->fetcher()->get('https://kitob.uz/a');
        } finally {
            $heldByAnotherWorker->release();
        }
    }

    public function test_the_page_is_not_requested_while_another_worker_holds_the_domain(): void
    {
        config(['scraping.lock_wait' => 1]);
        $this->fakeSite(['https://kitob.uz/a' => Http::response('a')]);

        $held = Cache::lock('scraping:domain:kitob.uz', 10);
        $held->get();

        try {
            $this->fetcher()->get('https://kitob.uz/a');
        } catch (FetchFailedException) {
            // expected
        } finally {
            $held->release();
        }

        Http::assertNotSent(fn (Request $request): bool => $request->url() === 'https://kitob.uz/a');
    }

    public function test_the_lock_is_released_after_a_fetch_so_the_next_one_gets_through(): void
    {
        $this->fakeSite([
            'https://kitob.uz/a' => Http::response('a'),
            'https://kitob.uz/b' => Http::response('b'),
        ]);

        $this->fetcher()->get('https://kitob.uz/a');

        $this->assertSame('b', $this->fetcher()->get('https://kitob.uz/b')->body);
    }

    public function test_the_lock_is_released_even_when_the_fetch_fails(): void
    {
        config(['scraping.retries' => 1]);
        $this->fakeSite([
            'https://kitob.uz/a' => Http::response('boom', 500),
            'https://kitob.uz/b' => Http::response('b'),
        ]);

        try {
            $this->fetcher()->get('https://kitob.uz/a');
        } catch (FetchFailedException) {
            // expected
        }

        $this->assertSame('b', $this->fetcher()->get('https://kitob.uz/b')->body);
    }
}
