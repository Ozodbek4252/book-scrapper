<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The pipeline stage an error happened in.
 */
enum ScrapeStage: string
{
    case Fetch = 'fetch';
    case Parse = 'parse';
    case Upsert = 'upsert';
}
