<?php

declare(strict_types=1);

namespace App\Scraping\Http;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Fetches and caches one robots.txt per domain.
 */
final readonly class Robots
{
    public function rulesFor(string $url): RobotsRules
    {
        if (! (bool) config('scraping.respect_robots', true)) {
            return RobotsRules::allowAll();
        }

        $host = self::hostOf($url);

        if ($host === null) {
            return RobotsRules::denyAll();
        }

        $ttl = (int) config('scraping.robots_cache_ttl', 86400);

        /** @var string $body */
        $body = Cache::remember(
            "scraping:robots:{$host}",
            $ttl,
            fn (): string => $this->download($url),
        );

        return match ($body) {
            self::ALLOW_ALL => RobotsRules::allowAll(),
            self::DENY_ALL => RobotsRules::denyAll(),
            default => RobotsRules::parse($body, (string) config('scraping.user_agent', '')),
        };
    }

    public function allows(string $url): bool
    {
        $path = self::pathOf($url);

        return $path !== null && $this->rulesFor($url)->allows($path);
    }

    /**
     * A missing robots.txt means the whole site is open. A server error means
     * we do not know, and RFC 9309 says to stay off until we do.
     */
    private function download(string $url): string
    {
        $scheme = parse_url($url, PHP_URL_SCHEME) ?: 'https';
        $host = self::hostOf($url);

        try {
            $response = Http::withHeaders(['User-Agent' => (string) config('scraping.user_agent', '')])
                ->timeout((int) config('scraping.timeout', 20))
                ->get("{$scheme}://{$host}/robots.txt");
        } catch (ConnectionException) {
            return self::DENY_ALL;
        }

        if ($response->clientError()) {
            return self::ALLOW_ALL;
        }

        if (! $response->successful()) {
            return self::DENY_ALL;
        }

        return $response->body();
    }

    public static function hostOf(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? mb_strtolower($host) : null;
    }

    public static function pathOf(string $url): ?string
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['host'])) {
            return null;
        }

        $path = $parts['path'] ?? '/';

        return $path.(isset($parts['query']) ? '?'.$parts['query'] : '');
    }

    private const ALLOW_ALL = "\0allow-all";

    private const DENY_ALL = "\0deny-all";
}
