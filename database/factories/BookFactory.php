<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Book;
use App\Models\Publisher;
use Illuminate\Database\Eloquent\Factories\Factory;

use function App\Support\book_fingerprint;
use function App\Support\normalize_title;

/**
 * @extends Factory<Book>
 */
class BookFactory extends Factory
{
    /**
     * Latin and Cyrillic spellings of the same well known titles.
     *
     * @var array<int, array{0: string, 1: string}>
     */
    private const TITLES = [
        ['Oʻtkan kunlar', 'Ўткан кунлар'],
        ['Mehrobdan chayon', 'Меҳробдан чаён'],
        ['Kecha va kunduz', 'Кеча ва кундуз'],
        ['Shaytanat', 'Шайтанат'],
        ['Dunyoning ishlari', 'Дунёнинг ишлари'],
        ['Ikki eshik orasi', 'Икки эшик ораси'],
        ['Ulugʻbek xazinasi', 'Улуғбек хазинаси'],
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        [$latin, $cyrillic] = fake()->randomElement(self::TITLES);

        return [
            'isbn13' => self::isbn13(),
            'isbn10' => null,
            'title' => $latin,
            'title_latin' => $latin,
            'title_cyrillic' => $cyrillic,
            'title_normalized' => normalize_title($latin),
            'subtitle' => null,
            'publisher_id' => Publisher::factory(),
            'published_year' => fake()->numberBetween(1990, 2025),
            'pages' => fake()->numberBetween(64, 720),
            'language' => fake()->randomElement(['uz', 'uz-Cyrl', 'ru']),
            'description' => fake()->optional()->paragraph(),
            'cover_url' => fake()->optional()->imageUrl(),
            'cover_path' => null,
            // Only books without an ISBN carry a fingerprint.
            'fingerprint' => null,
            'verified' => false,
            'locked_fields' => null,
        ];
    }

    /**
     * A book no source gave an ISBN for, so it merges on its fingerprint.
     */
    public function withoutIsbn(): static
    {
        return $this->state(function (array $attributes): array {
            // The unique suffix keeps bulk creation from colliding on the
            // fingerprint, which is what two books with one title and one year
            // are supposed to do.
            $title = $attributes['title'].' '.fake()->unique()->numberBetween(1, 99999);

            return [
                'isbn13' => null,
                'isbn10' => null,
                'title' => $title,
                'title_normalized' => normalize_title($title),
                'fingerprint' => book_fingerprint($title, null, $attributes['published_year'] ?? null),
            ];
        });
    }

    public function verified(): static
    {
        return $this->state(fn (array $attributes): array => ['verified' => true]);
    }

    /**
     * @param  array<int, string>  $fields
     */
    public function withLockedFields(array $fields): static
    {
        return $this->state(fn (array $attributes): array => ['locked_fields' => $fields]);
    }

    /**
     * A valid ISBN-13 under the Uzbek 978-9943 prefix, check digit included.
     */
    private static function isbn13(): string
    {
        $body = '9789943'.str_pad((string) fake()->unique()->numberBetween(0, 99999), 5, '0', STR_PAD_LEFT);

        $sum = 0;
        foreach (str_split($body) as $index => $digit) {
            $sum += (int) $digit * ($index % 2 === 0 ? 1 : 3);
        }

        return $body.((10 - $sum % 10) % 10);
    }
}
