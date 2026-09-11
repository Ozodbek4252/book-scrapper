<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\FetchBookPageJob;
use App\Jobs\NormalizeAndUpsertJob;
use App\Models\ScrapeRun;
use App\Scraping\Contracts\SourceDriver;
use App\Scraping\DTO\RawBook;
use App\Scraping\SourceRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class FetchBookPageJobTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Regression test: Bus\Batch::add() bulk-inserts onto the batch's own
     * queue regardless of the added job's onQueue(), which used to leave
     * every upsert stuck behind the entire remaining fetch queue instead of
     * ahead of it — see FetchBookPageJob for the fix.
     */
    public function test_a_found_book_is_upserted_on_its_own_priority_queue(): void
    {
        Queue::fake();
        config(['scraping.sources.asaxiy_uz' => ['driver' => FoundBookDriver::class, 'enabled' => true]]);
        config(['scraping.queue_upserts' => 'scraping-upserts']);
        $run = ScrapeRun::factory()->create(['source_key' => 'asaxiy_uz']);

        (new FetchBookPageJob('asaxiy_uz', 'https://asaxiy.uz/product/1', $run->id))
            ->handle(app(SourceRegistry::class));

        Queue::assertPushedOn(
            'scraping-upserts',
            NormalizeAndUpsertJob::class,
            fn (NormalizeAndUpsertJob $job): bool => $job->sourceKey === 'asaxiy_uz' && $job->runId === $run->id,
        );
        $this->assertSame(1, $run->fresh()->pages_scraped);
    }

    public function test_a_page_with_no_book_is_skipped_without_an_upsert(): void
    {
        Queue::fake();
        config(['scraping.sources.asaxiy_uz' => ['driver' => NoBookDriver::class, 'enabled' => true]]);
        $run = ScrapeRun::factory()->create(['source_key' => 'asaxiy_uz']);

        (new FetchBookPageJob('asaxiy_uz', 'https://asaxiy.uz/product/1', $run->id))
            ->handle(app(SourceRegistry::class));

        Queue::assertNothingPushed();
        $this->assertSame(1, $run->fresh()->pages_scraped);
    }
}

class FoundBookDriver implements SourceDriver
{
    public function key(): string
    {
        return 'asaxiy_uz';
    }

    public function discover(): iterable
    {
        return [];
    }

    public function fetch(string $url): ?RawBook
    {
        return new RawBook(sourceKey: 'asaxiy_uz', url: $url, title: 'A book');
    }
}

class NoBookDriver implements SourceDriver
{
    public function key(): string
    {
        return 'asaxiy_uz';
    }

    public function discover(): iterable
    {
        return [];
    }

    public function fetch(string $url): ?RawBook
    {
        return null;
    }
}
