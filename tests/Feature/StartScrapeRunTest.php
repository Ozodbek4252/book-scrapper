<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ScrapeRunStatus;
use App\Jobs\DiscoverSourceJob;
use App\Models\ScrapeRun;
use App\Scraping\StartScrapeRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class StartScrapeRunTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_opens_a_pending_run_and_queues_discovery(): void
    {
        Queue::fake();

        $run = (new StartScrapeRun)->handle('asaxiy_uz');

        $this->assertSame('asaxiy_uz', $run->source_key);
        $this->assertSame(ScrapeRunStatus::Pending, $run->status);
        $this->assertNull($run->started_at);

        Queue::assertPushed(
            DiscoverSourceJob::class,
            fn (DiscoverSourceJob $job): bool => $job->sourceKey === 'asaxiy_uz' && $job->runId === $run->id,
        );
    }

    public function test_discovery_runs_on_its_own_queue_so_it_never_blocks_the_api(): void
    {
        Queue::fake();
        config(['scraping.queue' => 'scraping']);

        (new StartScrapeRun)->handle('asaxiy_uz');

        Queue::assertPushedOn('scraping', DiscoverSourceJob::class);
    }

    public function test_the_button_starts_a_run_and_sends_you_to_it(): void
    {
        Queue::fake();

        $response = $this->post(route('scrape-runs.store'), ['source_key' => 'asaxiy_uz']);

        $run = ScrapeRun::sole();
        $response->assertRedirect(route('scrape-runs.show', $run))
            ->assertSessionHas('status', 'Queued a run for asaxiy_uz.');
    }

    public function test_it_refuses_a_source_that_is_not_configured(): void
    {
        Queue::fake();

        $this->post(route('scrape-runs.store'), ['source_key' => 'some-other-shop.uz'])
            ->assertSessionHasErrors('source_key');

        $this->assertSame(0, ScrapeRun::count());
        Queue::assertNothingPushed();
    }

    public function test_it_requires_a_source(): void
    {
        $this->post(route('scrape-runs.store'), [])->assertSessionHasErrors('source_key');
    }

    public function test_the_run_list_offers_every_configured_source(): void
    {
        $this->get(route('scrape-runs.index'))
            ->assertOk()
            ->assertSee('Run now')
            ->assertSee('asaxiy_uz')
            ->assertSee('olcha_uz');
    }
}
