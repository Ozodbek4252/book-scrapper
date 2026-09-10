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
}
