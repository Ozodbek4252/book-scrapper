<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ScrapeStage;
use App\Models\ScrapeError;
use App\Models\ScrapeRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScrapeError>
 */
class ScrapeErrorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'run_id' => ScrapeRun::factory(),
            'url' => fake()->url(),
            'stage' => fake()->randomElement(ScrapeStage::cases()),
            'message' => fake()->sentence(),
            'context' => ['status' => 500],
        ];
    }

    public function atStage(ScrapeStage $stage): static
    {
        return $this->state(fn (array $attributes): array => ['stage' => $stage]);
    }
}
