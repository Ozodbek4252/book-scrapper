<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Catalogue\UpsertBookFromSource;
use App\Enums\ScrapeStage;
use App\Models\ScrapeError;
use App\Models\ScrapeRun;
use App\Scraping\DTO\RawBook;
use App\Scraping\SourceRegistry;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Cleans one raw record and folds it into the catalogue.
 *
 * Never touches the network, so it can be replayed over stored payloads after
 * a change to the normalization layer.
 */
class NormalizeAndUpsertJob implements ShouldQueue
{
    use Batchable, Queueable;

    public int $tries = 3;

    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly string $sourceKey,
        public readonly array $raw,
        public readonly ?int $runId = null,
    ) {}

    public function handle(SourceRegistry $registry, UpsertBookFromSource $upsert): void
    {
        $result = $upsert->handle(
            RawBook::fromArray($this->raw),
            $registry->trustLevel($this->sourceKey),
        );

        if ($this->runId !== null) {
            ScrapeRun::whereKey($this->runId)
                ->increment($result['created'] ? 'items_new' : 'items_updated');
        }
    }

    public function failed(?Throwable $exception): void
    {
        if ($this->runId === null) {
            return;
        }

        ScrapeError::create([
            'run_id' => $this->runId,
            'url' => $this->raw['url'] ?? null,
            'stage' => ScrapeStage::Upsert,
            'message' => $exception?->getMessage() ?? 'The upsert job failed without an exception.',
            'context' => ['source_key' => $this->sourceKey],
        ]);

        ScrapeRun::whereKey($this->runId)->increment('errors_count');
    }
}
