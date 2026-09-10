<?php

declare(strict_types=1);

namespace App\Scraping\DTO;

use Carbon\CarbonImmutable;

/**
 * One response exactly as it came back, before anything parses it.
 *
 * Every one of these is written to disk, so a parser can be rewritten and
 * re-run over the whole catalogue without sending a single request.
 */
final readonly class RawResponse
{
    public function __construct(
        public string $url,
        public int $status,
        public string $body,
        public CarbonImmutable $fetchedAt,
        public bool $fromCache = false,
    ) {}

    /**
     * @return array<mixed>|null
     */
    public function json(): ?array
    {
        $decoded = json_decode($this->body, true);

        return is_array($decoded) ? $decoded : null;
    }

    public function cached(): self
    {
        return new self($this->url, $this->status, $this->body, $this->fetchedAt, fromCache: true);
    }

    /**
     * @return array{url: string, status: int, body: string, fetched_at: string}
     */
    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'status' => $this->status,
            'body' => $this->body,
            'fetched_at' => $this->fetchedAt->toIso8601String(),
        ];
    }

    /**
     * @param  array{url: string, status: int, body: string, fetched_at: string}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['url'],
            (int) $data['status'],
            $data['body'],
            CarbonImmutable::parse($data['fetched_at']),
        );
    }
}
