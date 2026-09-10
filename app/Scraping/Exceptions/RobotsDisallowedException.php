<?php

declare(strict_types=1);

namespace App\Scraping\Exceptions;

/**
 * Their robots.txt says no. This is our own policy refusing, not their server
 * failing, so it is never retried.
 */
class RobotsDisallowedException extends ScrapingException
{
    public static function for(string $url): self
    {
        return new self("robots.txt disallows fetching [{$url}].");
    }
}
