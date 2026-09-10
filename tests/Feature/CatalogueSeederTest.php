<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ScrapeError;
use App\Models\ScrapeRun;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogueSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_seeded_run_reports_the_number_of_errors_it_actually_has(): void
    {
        $this->seed(CatalogueSeeder::class);

        $runs = ScrapeRun::all();
        $this->assertNotEmpty($runs);

        foreach ($runs as $run) {
            $this->assertSame(
                ScrapeError::where('run_id', $run->id)->count(),
                $run->errors_count,
                "Run {$run->id}: the errors_count column disagrees with its scrape_errors rows.",
            );
        }
    }

    public function test_it_seeds_more_than_one_source(): void
    {
        $this->seed(CatalogueSeeder::class);

        $this->assertGreaterThan(1, ScrapeRun::distinct()->count('source_key'));
    }
}
