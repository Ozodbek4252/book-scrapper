<?php

declare(strict_types=1);

namespace App\Scraping\Http;

use App\Scraping\DTO\RawResponse;
use App\Scraping\Exceptions\FetchFailedException;
use App\Scraping\Exceptions\RobotsDisallowedException;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/**
 * The only way this application talks to someone else's server.
 *
 * Politeness is not optional here, so it is enforced in one place rather than
 * left to each driver: robots.txt is honoured, one domain is never fetched in
 * parallel, requests to a domain are spaced out, and every response is written
 * to disk before anyone parses it.
 */
final readonly class Fetcher
{
    public function __construct(
        private Robots $robots,
        private ResponseCache $cache,
    ) {}

    /**
     * Fetch a URL, or return the copy already on disk.
     *
     * @param  bool  $refresh  Ignore the cached copy and go to the network.
     *
     * @throws RobotsDisallowedException|FetchFailedException
     */
    public function get(string $url, bool $refresh = false): RawResponse
    {
        $this->guardUrl($url);

        if (! $refresh) {
            $cached = $this->cache->get($url);

            if ($cached !== null) {
                return $cached;
            }
        }

        if (! $this->robots->allows($url)) {
            throw RobotsDisallowedException::for($url);
        }

        $response = $this->fetchPolitely($url);

        $this->cache->put($response);

        return $response;
    }

    /**
     * Whether a URL is already on disk, so a driver can re-parse without any
     * risk of touching the network.
     */
    public function isCached(string $url): bool
    {
        return $this->cache->get($url) !== null;
    }

    /**
     * Nothing that is not a plain public web page.
     */
    private function guardUrl(string $url): void
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['host'], $parts['scheme'])) {
            throw FetchFailedException::refused($url, 'it is not an absolute http(s) URL');
        }

        if (! in_array(mb_strtolower($parts['scheme']), ['http', 'https'], true)) {
            throw FetchFailedException::refused($url, 'only http and https are allowed');
        }

        // Credentials in a URL mean the page is behind a login, and this
        // scraper never signs in to anything.
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw FetchFailedException::refused($url, 'it carries credentials');
        }
    }

    /**
     * Hold the domain's lock for the whole request, so two workers can never
     * hit one site at the same time, and wait out the gap since the last one.
     */
    private function fetchPolitely(string $url): RawResponse
    {
        $host = Robots::hostOf($url) ?? '';

        // How long a worker waits for its turn on a domain before giving up.
        $wait = max((int) config('scraping.lock_wait', 60), 1);
        $lock = Cache::lock("scraping:domain:{$host}", $wait);

        try {
            $lock->block($wait);
        } catch (LockTimeoutException) {
            throw FetchFailedException::refused($url, "another worker held [{$host}] for too long");
        }

        try {
            $this->waitForTurn($host, $url);

            $response = $this->send($url);

            Cache::put("scraping:last-request:{$host}", microtime(true), 300);

            return $response;
        } finally {
            $lock->release();
        }
    }

    /**
     * Space requests out. A Crawl-delay in their robots.txt wins if it asks for
     * more room than our own rate limit does.
     */
    private function waitForTurn(string $host, string $url): void
    {
        $perSecond = max((int) config('scraping.rate_limit', 1), 1);
        $gap = 1 / $perSecond;

        $crawlDelay = $this->robots->rulesFor($url)->crawlDelay();

        if ($crawlDelay !== null && $crawlDelay > $gap) {
            $gap = $crawlDelay;
        }

        $lastAt = Cache::get("scraping:last-request:{$host}");

        if (! is_float($lastAt) && ! is_int($lastAt)) {
            return;
        }

        $elapsed = microtime(true) - (float) $lastAt;

        if ($elapsed < $gap) {
            Sleep::for((int) round(($gap - $elapsed) * 1000))->milliseconds();
        }
    }

    private function send(string $url): RawResponse
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => (string) config('scraping.user_agent', ''),
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,application/json;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'uz,ru;q=0.8,en;q=0.6',
            ])
                ->timeout((int) config('scraping.timeout', 20))
                ->retry((int) config('scraping.retries', 3), 1000, throw: false)
                ->get($url);
        } catch (ConnectionException $exception) {
            throw FetchFailedException::transport($url, $exception);
        }

        if (! $response->successful()) {
            throw FetchFailedException::status($url, $response->status());
        }

        return new RawResponse(
            url: $url,
            status: $response->status(),
            body: $response->body(),
            fetchedAt: CarbonImmutable::now(),
        );
    }
}
