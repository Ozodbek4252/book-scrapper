<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\ScrapeRunStatus;
use App\Enums\ScrapeStage;
use App\Models\ScrapeError;
use App\Models\ScrapeRun;
use App\Scraping\SourceRegistry;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;
use Throwable;

/**
 * Walks one source's catalogue and records what it found.
 *
 * This is the head of the pipeline. Fetching each discovered page, and the
 * FetchBookPageJob that does it, arrive with the first real driver.
 */
class DiscoverSourceJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /**
     * Walking a whole catalogue's category pages at one request per second
     * takes minutes. This must stay below the queue's retry_after.
     */
    public int $timeout = 1800;

    /**
     * Release the uniqueness lock after an hour so a crashed worker cannot
     * block a source forever.
     */
    public int $uniqueFor = 3600;

    public function __construct(
        public readonly string $sourceKey,
        public readonly int $runId,
    ) {}

    /**
     * One discovery per source at a time. Never two crawlers on one domain.
     */
    public function uniqueId(): string
    {
        return $this->sourceKey;
    }

    public function handle(SourceRegistry $registry): void
    {
        $run = ScrapeRun::find($this->runId);

        if ($run === null) {
            return;
        }

        $run->update([
            'status' => ScrapeRunStatus::Running,
            'started_at' => now(),
        ]);

        if (! $registry->scrapingEnabled()) {
            $this->stop($run, 'Scraping is switched off globally (scraping.enabled).');

            return;
        }

        if (! $registry->isEnabled($this->sourceKey)) {
            $this->stop($run, "Source [{$this->sourceKey}] is disabled in config/scraping.php.");

            return;
        }

        $driver = $registry->driverFor($this->sourceKey);

        if ($driver === null) {
            $this->stop($run, "No driver is written for source [{$this->sourceKey}] yet.");

            return;
        }

        // Zero means take the whole catalogue.
        $limit = (int) config('scraping.max_pages_per_run', 200);
        $jobs = [];

        foreach ($driver->discover() as $url) {
            $jobs[] = new FetchBookPageJob($this->sourceKey, $url, $run->id);

            if ($limit > 0 && count($jobs) >= $limit) {
                break;
            }
        }

        $run->update(['items_found' => count($jobs)]);

        if ($jobs === []) {
            $this->finish($run);

            return;
        }

        $runId = $run->id;

        Bus::batch($jobs)
            ->name("scrape:{$this->sourceKey}:{$runId}")
            ->onQueue((string) config('scraping.queue'))
            // One bad page must not abandon the rest of the catalogue.
            ->allowFailures()
            ->finally(function () use ($runId): void {
                $run = ScrapeRun::find($runId);

                $run?->update([
                    'status' => $run->errors_count > 0 && $run->items_new + $run->items_updated === 0
                        ? ScrapeRunStatus::Failed
                        : ScrapeRunStatus::Completed,
                    'finished_at' => now(),
                ]);
            })
            ->dispatch();
    }

    private function finish(ScrapeRun $run): void
    {
        $run->update([
            'status' => ScrapeRunStatus::Completed,
            'finished_at' => now(),
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        $run = ScrapeRun::find($this->runId);

        if ($run === null) {
            return;
        }

        $this->stop(
            $run,
            $exception?->getMessage() ?? 'The discovery job failed without an exception.',
            ['exception' => $exception === null ? null : $exception::class],
        );
    }

    /**
     * End the run as failed and leave behind a reason someone can read.
     *
     * @param  array<string, mixed>  $context
     */
    private function stop(ScrapeRun $run, string $message, array $context = []): void
    {
        ScrapeError::create([
            'run_id' => $run->id,
            'url' => null,
            'stage' => ScrapeStage::Fetch,
            'message' => $message,
            'context' => $context + ['source_key' => $this->sourceKey],
        ]);

        $run->update([
            'status' => ScrapeRunStatus::Failed,
            'finished_at' => now(),
            'errors_count' => $run->errors()->count(),
        ]);
    }
}
