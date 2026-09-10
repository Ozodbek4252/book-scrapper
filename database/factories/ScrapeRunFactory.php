<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ScrapeRunStatus;
use App\Models\ScrapeRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScrapeRun>
 */
class ScrapeRunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source_key' => 'kitob_uz',
            'status' => ScrapeRunStatus::Pending,
            'started_at' => null,
            'finished_at' => null,
            'pages_scraped' => 0,
            'items_found' => 0,
            'items_new' => 0,
            'items_updated' => 0,
            'errors_count' => 0,
        ];
    }

    public function running(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ScrapeRunStatus::Running,
            'started_at' => now()->subMinutes(5),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ScrapeRunStatus::Completed,
            'started_at' => now()->subMinutes(30),
            'finished_at' => now(),
            'pages_scraped' => fake()->numberBetween(1, 200),
            'items_found' => $found = fake()->numberBetween(1, 500),
            'items_new' => $new = fake()->numberBetween(0, $found),
            'items_updated' => $found - $new,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ScrapeRunStatus::Failed,
            'started_at' => now()->subMinutes(10),
            'finished_at' => now(),
            'errors_count' => fake()->numberBetween(1, 20),
        ]);
    }
}
