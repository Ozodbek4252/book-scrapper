<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Scraping\Exceptions\FetchFailedException;
use App\Scraping\Exceptions\RobotsDisallowedException;
use App\Scraping\Http\Fetcher;
use App\Scraping\Http\ResponseCache;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FetcherTest extends TestCase
{
    private const PAGE = 'https://kitob.uz/product/1';

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
     * robots.txt allowing everything, plus whatever page responses are given.
     *
     * @param  array<string, mixed>  $responses
     */
    private function fakeSite(array $responses = [], string $robots = "User-agent: *\nDisallow:"): void
    {
        Http::fake(['*/robots.txt' => Http::response($robots)] + $responses);
    }

    public function test_it_fetches_a_page_and_returns_the_body(): void
    {
        $this->fakeSite([self::PAGE => Http::response('<html>Oʻtkan kunlar</html>')]);

        $response = $this->fetcher()->get(self::PAGE);

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('Oʻtkan kunlar', $response->body);
        $this->assertFalse($response->fromCache);
    }

    public function test_it_sends_a_descriptive_user_agent(): void
    {
        config(['scraping.user_agent' => 'BookScraperBot/1.0 (+https://example.uz/bot)']);
        $this->fakeSite([self::PAGE => Http::response('ok')]);

        $this->fetcher()->get(self::PAGE);

        Http::assertSent(fn (Request $request): bool => $request->url() !== self::PAGE
            || $request->header('User-Agent')[0] === 'BookScraperBot/1.0 (+https://example.uz/bot)');
    }

    public function test_the_second_fetch_of_a_url_never_touches_the_network(): void
    {
        $this->fakeSite([self::PAGE => Http::response('first copy')]);

        $this->fetcher()->get(self::PAGE);
        $requestsAfterFirst = count(Http::recorded());

        $again = $this->fetcher()->get(self::PAGE);

        $this->assertTrue($again->fromCache);
        $this->assertSame('first copy', $again->body);
        $this->assertCount($requestsAfterFirst, Http::recorded(), 'a cached URL must not be requested again');
    }

    public function test_the_raw_body_is_written_to_disk_keyed_by_url_hash(): void
    {
        $this->fakeSite([self::PAGE => Http::response('<html>raw</html>')]);

        $this->fetcher()->get(self::PAGE);

        $path = app(ResponseCache::class)->pathFor(self::PAGE);

        Storage::disk('local')->assertExists($path);
        $this->assertStringContainsString(sha1(self::PAGE), $path);
        $this->assertStringContainsString('<html>raw</html>', (string) Storage::disk('local')->get($path));
    }

    public function test_a_refresh_goes_back_to_the_network(): void
    {
        // A sequence, not a second Http::fake() call: fake() merges stubs, so
        // re-faking the same URL leaves the original response in front.
        $this->fakeSite([self::PAGE => Http::sequence()->push('first')->push('second')]);

        $this->assertSame('first', $this->fetcher()->get(self::PAGE)->body);
        $this->assertSame('second', $this->fetcher()->get(self::PAGE, refresh: true)->body);
        $this->assertSame('second', $this->fetcher()->get(self::PAGE)->body, 'the refreshed copy should replace the cached one');
    }

    public function test_it_refuses_a_page_robots_disallows(): void
    {
        $this->fakeSite([self::PAGE => Http::response('should never be read')], "User-agent: *\nDisallow: /product");

        $this->expectException(RobotsDisallowedException::class);

        $this->fetcher()->get(self::PAGE);
    }

    public function test_a_disallowed_page_is_never_requested(): void
    {
        $this->fakeSite([self::PAGE => Http::response('should never be read')], "User-agent: *\nDisallow: /");

        try {
            $this->fetcher()->get(self::PAGE);
        } catch (RobotsDisallowedException) {
            // expected
        }

        Http::assertNotSent(fn (Request $request): bool => $request->url() === self::PAGE);
    }

    public function test_a_missing_robots_file_opens_the_site(): void
    {
        Http::fake([
            '*/robots.txt' => Http::response('Not Found', 404),
            self::PAGE => Http::response('ok'),
        ]);

        $this->assertSame('ok', $this->fetcher()->get(self::PAGE)->body);
    }

    public function test_a_broken_robots_file_keeps_us_off_the_site(): void
    {
        Http::fake([
            '*/robots.txt' => Http::response('Server Error', 500),
            self::PAGE => Http::response('ok'),
        ]);

        $this->expectException(RobotsDisallowedException::class);

        $this->fetcher()->get(self::PAGE);
    }

    public function test_robots_is_fetched_once_per_domain(): void
    {
        $this->fakeSite([
            'https://kitob.uz/product/1' => Http::response('a'),
            'https://kitob.uz/product/2' => Http::response('b'),
        ]);

        $this->fetcher()->get('https://kitob.uz/product/1');
        $this->fetcher()->get('https://kitob.uz/product/2');

        $robotsRequests = collect(Http::recorded())
            ->filter(fn (array $pair): bool => str_ends_with($pair[0]->url(), '/robots.txt'))
            ->count();

        $this->assertSame(1, $robotsRequests);
    }

    public function test_it_raises_a_fetch_failure_on_a_server_error(): void
    {
        config(['scraping.retries' => 1]);
        $this->fakeSite([self::PAGE => Http::response('boom', 503)]);

        $this->expectException(FetchFailedException::class);
        $this->expectExceptionMessage('HTTP 503');

        $this->fetcher()->get(self::PAGE);
    }

    public function test_a_failed_fetch_is_not_cached(): void
    {
        config(['scraping.retries' => 1]);
        $this->fakeSite([self::PAGE => Http::response('boom', 503)]);

        try {
            $this->fetcher()->get(self::PAGE);
        } catch (FetchFailedException) {
            // expected
        }

        Storage::disk('local')->assertMissing(app(ResponseCache::class)->pathFor(self::PAGE));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function refusedUrls(): array
    {
        return [
            'not absolute' => ['/product/1', 'absolute http(s) URL'],
            'wrong scheme' => ['ftp://kitob.uz/x', 'only http and https'],
            'file scheme' => ['file:///etc/passwd', 'absolute http(s) URL'],
            'carries credentials' => ['https://user:secret@kitob.uz/x', 'carries credentials'],
        ];
    }

    #[DataProvider('refusedUrls')]
    public function test_it_refuses_urls_it_should_never_touch(string $url, string $reason): void
    {
        $this->fakeSite();

        $this->expectException(FetchFailedException::class);
        $this->expectExceptionMessage($reason);

        $this->fetcher()->get($url);
    }

    public function test_it_never_signs_in_so_a_credentialed_url_is_never_requested(): void
    {
        $this->fakeSite();

        try {
            $this->fetcher()->get('https://user:secret@kitob.uz/x');
        } catch (FetchFailedException) {
            // expected
        }

        Http::assertNothingSent();
    }
}
