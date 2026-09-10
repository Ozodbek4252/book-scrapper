<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\ScrapeStage;
use App\Models\ScrapeError;
use App\Models\ScrapeRun;
use App\Scraping\SourceRegistry;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Reads one product page and hands what it found to the upsert job.
 *
 * The fetcher does the waiting and the robots check, so this job only has to
 * worry about what happens when a page will not parse.
 */
class FetchBookPageJob implements ShouldBeUnique, ShouldQueue
{
    use Batchable, Queueable;

    public int $tries = 3;

    /**
     * One job per URL. A catalogue walk that overlaps a nightly run should not
     * fetch the same page twice.
     */
    public int $uniqueFor = 3600;

    public function __construct(
        public readonly string $sourceKey,
        public readonly string $url,
        public readonly int $runId,
    ) {}

    public function uniqueId(): string
    {
        return sha1($this->url);
    }

    /**
     * Back off in growing steps: their server is having a bad time, not us.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(SourceRegistry $registry): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $driver = $registry->driverFor($this->sourceKey);

        if ($driver === null) {
            return;
        }

        $raw = $driver->fetch($this->url);

        ScrapeRun::whereKey($this->runId)->increment('pages_scraped');

        // Not every product on a general shop is a book.
        if ($raw === null) {
            return;
        }

        $upsert = (new NormalizeAndUpsertJob($this->sourceKey, $raw->toArray(), $this->runId))
            ->onQueue((string) config('scraping.queue_upserts'));

        // Add it to this run's batch rather than dispatching alongside it, so
        // the run is not reported finished while its books are still landing.
        if ($this->batch() !== null) {
            $this->batch()->add([$upsert]);

            return;
        }

        dispatch($upsert);
    }

    public function failed(?Throwable $exception): void
    {
        ScrapeError::create([
            'run_id' => $this->runId,
            'url' => $this->url,
            'stage' => ScrapeStage::Fetch,
            'message' => $exception?->getMessage() ?? 'The fetch job failed without an exception.',
            'context' => ['source_key' => $this->sourceKey],
        ]);

        ScrapeRun::whereKey($this->runId)->increment('errors_count');
    }
}
