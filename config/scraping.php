<?php

declare(strict_types=1);

use App\Enums\TrustLevel;
use App\Scraping\Drivers\AsaxiyUzDriver;
use App\Scraping\Drivers\OlchaUzDriver;

return [

    /*
    |--------------------------------------------------------------------------
    | Master switch
    |--------------------------------------------------------------------------
    |
    | Turning this off stops every source at once, without a deploy.
    |
    */

    'enabled' => env('SCRAPING_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Identifying ourselves
    |--------------------------------------------------------------------------
    |
    | Say who we are and give a page a site owner can read to find out what this
    | traffic is and how to reach us.
    |
    */

    'user_agent' => env(
        'SCRAPING_USER_AGENT',
        'BookScraperBot/1.0 (+'.env('APP_URL', 'http://localhost').'/bot)',
    ),

    /*
    |--------------------------------------------------------------------------
    | Politeness
    |--------------------------------------------------------------------------
    |
    | One request per second per domain, and never two at once against the same
    | domain. A source may lower its own rate below, but not raise it past the
    | ceiling here.
    |
    */

    'rate_limit' => (int) env('SCRAPING_RATE_LIMIT', 1),
    'respect_robots' => env('SCRAPING_RESPECT_ROBOTS', true),
    'robots_cache_ttl' => (int) env('SCRAPING_ROBOTS_CACHE_TTL', 86400),
    'timeout' => (int) env('SCRAPING_TIMEOUT', 20),
    // Seconds a worker waits for its turn on a domain before giving up.
    'lock_wait' => (int) env('SCRAPING_LOCK_WAIT', 60),
    'retries' => (int) env('SCRAPING_RETRIES', 3),

    /*
    |--------------------------------------------------------------------------
    | Raw response cache
    |--------------------------------------------------------------------------
    |
    | Every response is written here, keyed by a hash of its URL, before anything
    | parses it. Re-parsing then costs nothing and never touches their servers.
    |
    */

    'cache' => [
        'disk' => env('SCRAPING_CACHE_DISK', 'local'),
        'path' => env('SCRAPING_CACHE_PATH', 'scraping/raw'),
        'ttl' => (int) env('SCRAPING_CACHE_TTL', 604800),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    |
    | Scraping runs on its own queue so a slow catalogue walk never delays the
    | API's own jobs.
    |
    */

    'queue' => env('SCRAPING_QUEUE', 'scraping'),

    /*
    | Upserts go on their own queue, listed ahead of the fetch queue on the
    | worker. Fetching a whole catalogue takes hours, and without this the
    | thousands of books already fetched would sit behind every remaining
    | request instead of landing as they arrive.
    */

    'queue_upserts' => env('SCRAPING_QUEUE_UPSERTS', 'scraping-upserts'),

    /*
    |--------------------------------------------------------------------------
    | How much one run crawls
    |--------------------------------------------------------------------------
    |
    | A full catalogue is tens of thousands of pages, and at one request per
    | second that is most of a day. A run stops after this many product pages
    | so a manual run finishes while you are still watching it. Set it to 0
    | to take the whole catalogue.
    |
    */

    'max_pages_per_run' => (int) env('SCRAPING_MAX_PAGES_PER_RUN', 200),

    /**
     * Requests per minute per API token.
     */
    'api_rate_limit' => (int) env('API_RATE_LIMIT', 60),

    /*
    |--------------------------------------------------------------------------
    | Sources
    |--------------------------------------------------------------------------
    |
    | One entry per site. 'driver' stays null until that site's driver is
    | written, and 'enabled' is the per-source kill switch. 'trust_level'
    | decides who wins when two sources disagree about the same field.
    |
    */

    'sources' => [

        // Not scraped. Books sent in by mobile app users land here, trusted
        // least, so any real source outranks them field by field.
        'user_submission' => [
            'driver' => null,
            'enabled' => false,
            'base_url' => null,
            'rate_limit' => 1,
            'trust_level' => TrustLevel::UserSubmission,
        ],

        'asaxiy_uz' => [
            'driver' => AsaxiyUzDriver::class,
            'enabled' => env('SCRAPING_ASAXIY_ENABLED', false),
            'base_url' => 'https://asaxiy.uz',
            'rate_limit' => 1,
            'trust_level' => TrustLevel::Bookstore,
        ],

        // No ISBNs at all, so it can never answer a barcode scan. It is here
        // to enrich books already known from a source that has them, and to
        // cover titles that source misses.
        'olcha_uz' => [
            'driver' => OlchaUzDriver::class,
            'enabled' => env('SCRAPING_OLCHA_ENABLED', false),
            'base_url' => 'https://olcha.uz',
            'rate_limit' => 1,
            'trust_level' => TrustLevel::Bookstore,
        ],

        'ozbekiston_nmiu' => [
            'driver' => null,
            'enabled' => false,
            'base_url' => null,
            'rate_limit' => 1,
            'trust_level' => TrustLevel::Publisher,
        ],

        'yangi_asr_avlodi' => [
            'driver' => null,
            'enabled' => false,
            'base_url' => null,
            'rate_limit' => 1,
            'trust_level' => TrustLevel::Publisher,
        ],

        'akademnashr' => [
            'driver' => null,
            'enabled' => false,
            'base_url' => null,
            'rate_limit' => 1,
            'trust_level' => TrustLevel::Publisher,
        ],

    ],

];
