<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Author;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Author>
 */
class AuthorFactory extends Factory
{
    /**
     * Given name, family name, and the Cyrillic spelling of the same person.
     *
     * @var array<int, array{0: string, 1: string, 2: string}>
     */
    private const AUTHORS = [
        ['Abdulla', 'Qodiriy', 'Абдулла Қодирий'],
        ['Choʻlpon', 'Abdulhamid', 'Чўлпон Абдулҳамид'],
        ['Oybek', 'Musa Toshmuhammad', 'Ойбек Мусо Тошмуҳаммад'],
        ['Said', 'Ahmad', 'Саид Аҳмад'],
        ['Oʻtkir', 'Hoshimov', 'Ўткир Ҳошимов'],
        ['Erkin', 'Aʼzam', 'Эркин Аъзам'],
        ['Togʻay', 'Murod', 'Тоғай Мурод'],
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        [$given, $family, $cyrillic] = fake()->randomElement(self::AUTHORS);
        $full = "{$given} {$family}";

        return [
            'family_name' => $family,
            'given_name' => $given,
            'full_name' => $full,
            'full_name_latin' => $full,
            'full_name_cyrillic' => $cyrillic,
            'full_name_normalized' => Str::squish(Str::lower($full)),
        ];
    }

    /**
     * An author a source gave as one unsplit string.
     */
    public function unsplit(): static
    {
        return $this->state(fn (array $attributes): array => [
            'family_name' => null,
            'given_name' => null,
        ]);
    }
}
