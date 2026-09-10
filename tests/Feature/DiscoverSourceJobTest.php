<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ScrapeRunStatus;
use App\Enums\ScrapeStage;
use App\Jobs\DiscoverSourceJob;
use App\Models\ScrapeRun;
use App\Scraping\Contracts\SourceDriver;
use App\Scraping\DTO\RawBook;
use App\Scraping\SourceRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscoverSourceJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_fails_the_run_when_no_driver_is_written_yet(): void
    {
        config(['scraping.sources.asaxiy_uz' => ['driver' => null, 'enabled' => true]]);

        $run = $this->runDiscoveryFor('asaxiy_uz');

        $this->assertSame(ScrapeRunStatus::Failed, $run->status);
        $this->assertStringContainsString('No driver is written', $run->errors->first()->message);
        $this->assertSame(ScrapeStage::Fetch, $run->errors->first()->stage);
    }

    public function test_it_fails_the_run_when_the_source_is_switched_off(): void
    {
        config(['scraping.sources.asaxiy_uz' => ['driver' => FakeDriver::class, 'enabled' => false]]);

        $run = $this->runDiscoveryFor('asaxiy_uz');

        $this->assertSame(ScrapeRunStatus::Failed, $run->status);
        $this->assertStringContainsString('is disabled', $run->errors->first()->message);
    }

    public function test_the_global_kill_switch_stops_a_run_dead(): void
    {
        config([
            'scraping.enabled' => false,
            'scraping.sources.asaxiy_uz' => ['driver' => FakeDriver::class, 'enabled' => true],
        ]);

        $run = $this->runDiscoveryFor('asaxiy_uz');

        $this->assertSame(ScrapeRunStatus::Failed, $run->status);
        $this->assertStringContainsString('switched off globally', $run->errors->first()->message);
    }

    public function test_it_completes_and_counts_what_discovery_yielded(): void
    {
        config(['scraping.sources.asaxiy_uz' => ['driver' => FakeDriver::class, 'enabled' => true]]);

        $run = $this->runDiscoveryFor('asaxiy_uz');

        $this->assertSame(ScrapeRunStatus::Completed, $run->status);
        $this->assertSame(3, $run->items_found);
        $this->assertSame(0, $run->errors_count);
        $this->assertNotNull($run->started_at);
        $this->assertNotNull($run->finished_at);
    }

    public function test_a_failed_run_reports_the_number_of_errors_it_really_has(): void
    {
        config(['scraping.sources.asaxiy_uz' => ['driver' => null, 'enabled' => true]]);

        $run = $this->runDiscoveryFor('asaxiy_uz');

        $this->assertSame($run->errors()->count(), $run->errors_count);
    }

    public function test_it_survives_a_run_that_was_deleted_before_the_worker_picked_it_up(): void
    {
        config(['scraping.sources.asaxiy_uz' => ['driver' => null, 'enabled' => true]]);

        (new DiscoverSourceJob('asaxiy_uz', 999))->handle(app(SourceRegistry::class));

        $this->assertSame(0, ScrapeRun::count());
    }

    private function runDiscoveryFor(string $sourceKey): ScrapeRun
    {
        $run = ScrapeRun::factory()->create(['source_key' => $sourceKey]);

        (new DiscoverSourceJob($sourceKey, $run->id))->handle(app(SourceRegistry::class));

        return $run->fresh()->load('errors');
    }
}

class FakeDriver implements SourceDriver
{
    public function key(): string
    {
        return 'asaxiy_uz';
    }

    public function discover(): iterable
    {
        yield 'https://kitob.uz/product/1';
        yield 'https://kitob.uz/product/2';
        yield 'https://kitob.uz/product/3';
    }

    public function fetch(string $url): ?RawBook
    {
        return null;
    }
}
