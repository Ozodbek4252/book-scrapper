<?php

declare(strict_types=1);

namespace App\Scraping;

use App\Enums\ScrapeRunStatus;
use App\Jobs\DiscoverSourceJob;
use App\Models\ScrapeRun;

/**
 * Opens a run for a source and puts it on the scraping queue.
 *
 * Lives outside the controller so the scheduler and a console command can
 * start a run the same way the button does.
 */
final readonly class StartScrapeRun
{
    public function handle(string $sourceKey): ScrapeRun
    {
        $run = ScrapeRun::create([
            'source_key' => $sourceKey,
            'status' => ScrapeRunStatus::Pending,
        ]);

        DiscoverSourceJob::dispatch($sourceKey, $run->id)
            ->onQueue(config('scraping.queue'));

        return $run;
    }
}
