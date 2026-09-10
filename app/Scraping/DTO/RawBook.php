<?php

declare(strict_types=1);

namespace App\Scraping\DTO;

/**
 * A book exactly as a source presented it. Nothing here is cleaned, parsed or
 * validated: years and page counts stay strings because a site may well say
 * "2019-yil" or "320 bet".
 *
 * Cleaning happens later, in the normalization layer, so it can be re-run over
 * a stored raw_payload without scraping the site again.
 */
final readonly class RawBook
{
    /**
     * @param  array<int, string>  $authors  Author names as printed, in the order shown.
     * @param  array<string, mixed>  $payload  The whole raw record, stored forever.
     */
    public function __construct(
        public string $sourceKey,
        public string $url,
        public ?string $externalId = null,
        public ?string $title = null,
        public ?string $subtitle = null,
        public array $authors = [],
        public ?string $publisher = null,
        public ?string $isbn = null,
        public ?string $publishedYear = null,
        public ?string $pages = null,
        public ?string $language = null,
        public ?string $description = null,
        public ?string $coverUrl = null,
        public ?string $price = null,
        public ?bool $inStock = null,
        public array $payload = [],
    ) {}

    /**
     * Stored verbatim on book_sources.raw_payload and kept forever, so the
     * canonical record can be rebuilt after a normalization change without
     * scraping anything again.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'source_key' => $this->sourceKey,
            'url' => $this->url,
            'external_id' => $this->externalId,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'authors' => $this->authors,
            'publisher' => $this->publisher,
            'isbn' => $this->isbn,
            'published_year' => $this->publishedYear,
            'pages' => $this->pages,
            'language' => $this->language,
            'description' => $this->description,
            'cover_url' => $this->coverUrl,
            'price' => $this->price,
            'in_stock' => $this->inStock,
            'payload' => $this->payload,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            sourceKey: (string) ($data['source_key'] ?? ''),
            url: (string) ($data['url'] ?? ''),
            externalId: $data['external_id'] ?? null,
            title: $data['title'] ?? null,
            subtitle: $data['subtitle'] ?? null,
            authors: $data['authors'] ?? [],
            publisher: $data['publisher'] ?? null,
            isbn: $data['isbn'] ?? null,
            publishedYear: $data['published_year'] ?? null,
            pages: $data['pages'] ?? null,
            language: $data['language'] ?? null,
            description: $data['description'] ?? null,
            coverUrl: $data['cover_url'] ?? null,
            price: $data['price'] ?? null,
            inStock: $data['in_stock'] ?? null,
            payload: $data['payload'] ?? [],
        );
    }
}
