<?php

declare(strict_types=1);

namespace App\Scraping\Http;

use App\Scraping\DTO\RawResponse;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use JsonException;

/**
 * Every raw response, on disk, keyed by a hash of its URL.
 *
 * This is what makes parser work cheap: re-running a rewritten parser over the
 * whole catalogue costs no requests, and their servers never notice.
 */
final readonly class ResponseCache
{
    public function get(string $url): ?RawResponse
    {
        $path = $this->pathFor($url);
        $disk = $this->disk();

        if (! $disk->exists($path)) {
            return null;
        }

        $contents = $disk->get($path);

        if ($contents === null) {
            return null;
        }

        $contents = self::decompress($contents);

        try {
            /** @var array{url: string, status: int, body: string, fetched_at: string} $data */
            $data = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $disk->delete($path);

            return null;
        }

        $response = RawResponse::fromArray($data);

        if ($this->hasExpired($response)) {
            return null;
        }

        return $response->cached();
    }

    public function put(RawResponse $response): void
    {
        $json = (string) json_encode($response->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        // A product page is around 500 KB and compresses to about a tenth of
        // that. Over a full catalogue this is the difference between 5 GB and
        // 500 MB, so the cache is always written compressed.
        $this->disk()->put($this->pathFor($response->url), (string) gzencode($json, 6));
    }

    /**
     * Entries written before the cache was compressed are still readable.
     */
    private static function decompress(string $contents): string
    {
        if (! str_starts_with($contents, "\x1f\x8b")) {
            return $contents;
        }

        $plain = @gzdecode($contents);

        return $plain === false ? $contents : $plain;
    }

    public function forget(string $url): void
    {
        $this->disk()->delete($this->pathFor($url));
    }

    /**
     * Hash of the URL, sharded two characters deep so one directory never ends
     * up holding the whole catalogue.
     */
    public function pathFor(string $url): string
    {
        $hash = sha1($url);
        $root = trim((string) config('scraping.cache.path', 'scraping/raw'), '/');

        return "{$root}/".substr($hash, 0, 2)."/{$hash}.json";
    }

    private function hasExpired(RawResponse $response): bool
    {
        $ttl = (int) config('scraping.cache.ttl', 0);

        return $ttl > 0 && $response->fetchedAt->addSeconds($ttl)->isPast();
    }

    private function disk(): Filesystem
    {
        return Storage::disk((string) config('scraping.cache.disk', 'local'));
    }
}
