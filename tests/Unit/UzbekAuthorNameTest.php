<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function App\Support\author_names_match;
use function App\Support\normalize_author_name;
use function App\Support\split_author_name;

class UzbekAuthorNameTest extends TestCase
{
    /**
     * The same writer as different sites print them.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function samePerson(): array
    {
        return [
            'name order' => ['Abdulla Qodiriy', 'Qodiriy Abdulla'],
            'comma form' => ['Abdulla Qodiriy', 'Qodiriy, Abdulla'],
            'cyrillic' => ['Abdulla Qodiriy', 'Абдулла Қодирий'],
            'cyrillic reversed' => ['Abdulla Qodiriy', 'Қодирий Абдулла'],
            'case' => ['Abdulla Qodiriy', 'ABDULLA QODIRIY'],
            'extra spaces' => ['Abdulla Qodiriy', '  Abdulla   Qodiriy '],
            'apostrophe variant' => ['Oʻtkir Hoshimov', "O'tkir Hoshimov"],
            'apostrophe script' => ['Oʻtkir Hoshimov', 'Ўткир Ҳошимов'],
            'glottal stop' => ['Erkin Aʼzam', 'Эркин Аъзам'],
        ];
    }

    #[DataProvider('samePerson')]
    public function test_one_person_gets_one_key(string $a, string $b): void
    {
        $this->assertSame(normalize_author_name($a), normalize_author_name($b));
        $this->assertTrue(author_names_match($a, $b));
    }

    public function test_different_people_get_different_keys(): void
    {
        $this->assertNotSame(
            normalize_author_name('Abdulla Qodiriy'),
            normalize_author_name('Abdulla Oripov'),
        );
        $this->assertFalse(author_names_match('Abdulla Qodiriy', 'Abdulla Oripov'));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function initials(): array
    {
        return [
            'given initial' => ['A. Qodiriy', 'Abdulla Qodiriy'],
            'no full stop' => ['A Qodiriy', 'Abdulla Qodiriy'],
            'reversed' => ['Qodiriy A.', 'Abdulla Qodiriy'],
            'cyrillic initial' => ['А. Қодирий', 'Abdulla Qodiriy'],
            'other writer' => ['S. Ahmad', 'Said Ahmad'],
        ];
    }

    #[DataProvider('initials')]
    public function test_an_initial_matches_the_full_given_name(string $abbreviated, string $full): void
    {
        $this->assertTrue(author_names_match($abbreviated, $full));
    }

    public function test_an_initial_does_not_match_a_different_family_name(): void
    {
        $this->assertFalse(author_names_match('A. Qodiriy', 'A. Oripov'));
        $this->assertFalse(author_names_match('A. Qodiriy', 'Abdulla Oripov'));
    }

    public function test_a_different_initial_does_not_match(): void
    {
        $this->assertFalse(author_names_match('S. Qodiriy', 'Abdulla Qodiriy'));
    }

    /**
     * @return array<string, array{0: string, 1: ?string, 2: ?string}>
     */
    public static function splits(): array
    {
        return [
            'given then family' => ['Abdulla Qodiriy', 'Abdulla', 'Qodiriy'],
            'comma puts family first' => ['Qodiriy, Abdulla', 'Abdulla', 'Qodiriy'],
            'initial as given' => ['A. Qodiriy', 'A.', 'Qodiriy'],
            'patronymic stays with given' => ['Musa Toshmuhammad oʻgʻli Oybek', 'Musa Toshmuhammad oʻgʻli', 'Oybek'],
            'single word is not guessed at' => ['Oybek', null, null],
            'empty' => ['', null, null],
        ];
    }

    #[DataProvider('splits')]
    public function test_it_splits_a_name_only_when_the_source_makes_it_clear(
        string $name,
        ?string $given,
        ?string $family,
    ): void {
        $this->assertSame(['given' => $given, 'family' => $family], split_author_name($name));
    }

    public function test_it_normalizes_the_apostrophe_when_splitting(): void
    {
        $this->assertSame(
            ['given' => 'Oʻtkir', 'family' => 'Hoshimov'],
            split_author_name("O'tkir Hoshimov"),
        );
    }
}
