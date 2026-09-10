<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Book;
use App\Models\BookSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookSource>
 */
class BookSourceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $externalId = (string) fake()->unique()->numberBetween(1000, 999999);

        return [
            'book_id' => Book::factory(),
            'source_key' => 'asaxiy_uz',
            'external_id' => $externalId,
            'url' => "https://asaxiy.uz/product/{$externalId}",
            'raw_payload' => ['id' => $externalId, 'title' => fake()->sentence(3)],
            'price' => fake()->randomFloat(2, 15000, 250000),
            'in_stock' => fake()->boolean(80),
            'scraped_at' => now(),
        ];
    }

    public function forSource(string $sourceKey): static
    {
        return $this->state(fn (array $attributes): array => ['source_key' => $sourceKey]);
    }

    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes): array => ['in_stock' => false]);
    }
}
