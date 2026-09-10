<?php

declare(strict_types=1);

namespace App\Scraping\Exceptions;

use Throwable;

class FetchFailedException extends ScrapingException
{
    public static function status(string $url, int $status): self
    {
        return new self("Fetching [{$url}] returned HTTP {$status}.");
    }

    public static function transport(string $url, Throwable $previous): self
    {
        return new self("Could not reach [{$url}]: {$previous->getMessage()}", previous: $previous);
    }

    public static function refused(string $url, string $reason): self
    {
        return new self("Refusing to fetch [{$url}]: {$reason}");
    }
}
