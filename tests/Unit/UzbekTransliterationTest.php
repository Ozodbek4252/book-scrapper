<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function App\Support\cyrillic_to_latin;
use function App\Support\detect_script;
use function App\Support\latin_to_cyrillic;
use function App\Support\normalize_apostrophes;
use function App\Support\to_cyrillic;
use function App\Support\to_latin;

class UzbekTransliterationTest extends TestCase
{
    /**
     * Real titles as the two alphabets actually print them.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function titles(): array
    {
        return [
            'oʻ digraph' => ['Oʻtkan kunlar', 'Ўткан кунлар'],
            'h and yo' => ['Mehrobdan chayon', 'Меҳробдан чаён'],
            'ch mid word' => ['Kecha va kunduz', 'Кеча ва кундуз'],
            'sh at the start' => ['Shaytanat', 'Шайтанат'],
            'gʻ digraph' => ['Ulugʻbek xazinasi', 'Улуғбек хазинаси'],
            'ya and q' => ['Dunyoning ishlari', 'Дунёнинг ишлари'],
            'double vowel' => ['Ikki eshik orasi', 'Икки эшик ораси'],
            'word initial e' => ['Erkin Aʼzam', 'Эркин Аъзам'],
        ];
    }

    #[DataProvider('titles')]
    public function test_it_transliterates_latin_into_cyrillic(string $latin, string $cyrillic): void
    {
        $this->assertSame($cyrillic, latin_to_cyrillic($latin));
    }

    #[DataProvider('titles')]
    public function test_it_transliterates_cyrillic_into_latin(string $latin, string $cyrillic): void
    {
        $this->assertSame($latin, cyrillic_to_latin($cyrillic));
    }

    #[DataProvider('titles')]
    public function test_a_title_survives_a_round_trip(string $latin, string $cyrillic): void
    {
        $this->assertSame($latin, cyrillic_to_latin(latin_to_cyrillic($latin)));
        $this->assertSame($cyrillic, latin_to_cyrillic(cyrillic_to_latin($cyrillic)));
    }

    public function test_it_keeps_capitals(): void
    {
        $this->assertSame('Ўткан', latin_to_cyrillic('Oʻtkan'));
        $this->assertSame('ЎТКАН', latin_to_cyrillic('OʻTKAN'));
        $this->assertSame('Шайтанат', latin_to_cyrillic('Shaytanat'));
        $this->assertSame('ШАЙТАНАТ', latin_to_cyrillic('SHAYTANAT'));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function apostropheVariants(): array
    {
        return [
            'straight quote' => ["O'tkan kunlar"],
            'right single quote' => ['O’tkan kunlar'],
            'left single quote' => ['O‘tkan kunlar'],
            'backtick' => ['O`tkan kunlar'],
            'acute accent' => ['O´tkan kunlar'],
            'modifier apostrophe' => ['Oʼtkan kunlar'],
            'turned comma' => ['Oʻtkan kunlar'],
            'prime' => ['O′tkan kunlar'],
        ];
    }

    #[DataProvider('apostropheVariants')]
    public function test_every_apostrophe_variant_transliterates_the_same(string $written): void
    {
        $this->assertSame('Ўткан кунлар', latin_to_cyrillic($written));
    }

    #[DataProvider('apostropheVariants')]
    public function test_every_apostrophe_variant_collapses_to_one_character(string $written): void
    {
        $this->assertSame('Oʻtkan kunlar', normalize_apostrophes($written));
    }

    public function test_a_lone_apostrophe_is_a_tutuq_belgisi(): void
    {
        // The apostrophe in aʼzam is a glottal stop, not part of a digraph.
        $this->assertSame('аъзам', latin_to_cyrillic('aʼzam'));
        $this->assertSame('шеър', latin_to_cyrillic('sheʼr'));
        $this->assertSame('aʼzam', cyrillic_to_latin('аъзам'));
    }

    public function test_it_tells_the_two_alphabets_apart(): void
    {
        $this->assertSame('cyrillic', detect_script('Ўткан кунлар'));
        $this->assertSame('latin', detect_script('Oʻtkan kunlar'));
        $this->assertSame('unknown', detect_script('9789943012349'));
    }

    public function test_it_converts_only_when_it_needs_to(): void
    {
        $this->assertSame('Oʻtkan kunlar', to_latin('Ўткан кунлар'));
        $this->assertSame('Oʻtkan kunlar', to_latin("O'tkan kunlar"));
        $this->assertSame('Ўткан кунлар', to_cyrillic('Oʻtkan kunlar'));
        $this->assertSame('Ўткан кунлар', to_cyrillic('Ўткан кунлар'));
    }
}
