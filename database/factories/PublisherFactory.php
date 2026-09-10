<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Publisher;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Publisher>
 */
class PublisherFactory extends Factory
{
    /**
     * Real Uzbek publishing houses, so test data looks like production data.
     *
     * @var array<int, array{0: string, 1: string}>
     */
    private const NAMES = [
        ['Oʻzbekiston NMIU', 'Ўзбекистон НМИУ'],
        ['Yangi asr avlodi', 'Янги аср авлоди'],
        ['Akademnashr', 'Академнашр'],
        ['Sharq', 'Шарқ'],
        ['Gʻafur Gʻulom nomidagi nashriyot', 'Ғафур Ғулом номидаги нашриёт'],
        ['Maʼnaviyat', 'Маънавият'],
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        [$latin, $cyrillic] = fake()->randomElement(self::NAMES);

        return [
            'name' => $latin,
            'name_latin' => $latin,
            'name_cyrillic' => $cyrillic,
            'name_normalized' => Str::squish(Str::lower($latin)),
            'website' => fake()->optional()->url(),
        ];
    }
}
