<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function App\Support\book_fingerprint;
use function App\Support\normalize_title;
use function App\Support\strip_title_noise;

class UzbekTitleTest extends TestCase
{
    /**
     * Titles as shops actually list them, with the packaging notes attached.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function noisyTitles(): array
    {
        return [
            'hard cover' => ['Oʻtkan kunlar (qattiq muqova)', 'Oʻtkan kunlar'],
            'soft cover' => ['Shaytanat (yumshoq muqova)', 'Shaytanat'],
            'cyrillic cover note' => ['Ўткан кунлар (қаттиқ муқова)', 'Ўткан кунлар'],
            'edition number' => ['Mehrobdan chayon 2-nashr', 'Mehrobdan chayon'],
            'spaced edition' => ['Mehrobdan chayon 3 - nashr', 'Mehrobdan chayon'],
            'expanded edition' => ["Kecha va kunduz to'ldirilgan nashr", 'Kecha va kunduz'],
            'reprint' => ['Dunyoning ishlari qayta nashr', 'Dunyoning ishlari'],
            'bracketed series' => ['Ikki eshik orasi [Milliy roman]', 'Ikki eshik orasi'],
            'used copy' => ['Erica James: Airs & Graces (used)', 'Erica James: Airs & Graces'],
            'trailing comma' => ['Shaytanat (qattiq muqova),', 'Shaytanat'],
            'already clean' => ['Ulugʻbek xazinasi', 'Ulugʻbek xazinasi'],
        ];
    }

    #[DataProvider('noisyTitles')]
    public function test_it_strips_what_is_not_part_of_the_title(string $listed, string $expected): void
    {
        $this->assertSame($expected, strip_title_noise($listed));
    }

    public function test_it_does_not_eat_a_parenthesis_that_belongs_to_the_title(): void
    {
        $this->assertSame('Alpomish (doston)', strip_title_noise('Alpomish (doston)'));
    }

    /**
     * The whole point: the same book listed either way produces one key.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function sameBookTwoWays(): array
    {
        return [
            'script' => ['Oʻtkan kunlar', 'Ўткан кунлар'],
            'apostrophe' => ["O'tkan kunlar", 'Oʻtkan kunlar'],
            'curly apostrophe' => ['O’tkan kunlar', 'Oʻtkan kunlar'],
            'cover note' => ['Oʻtkan kunlar (qattiq muqova)', 'Ўткан кунлар'],
            'case' => ['OʻTKAN KUNLAR', 'oʻtkan kunlar'],
            'spacing' => ['Oʻtkan   kunlar ', 'Oʻtkan kunlar'],
            'punctuation' => ['Oʻtkan kunlar.', 'Oʻtkan kunlar'],
            'edition note' => ['Ўткан кунлар 2-нашр', 'Oʻtkan kunlar'],
        ];
    }

    #[DataProvider('sameBookTwoWays')]
    public function test_two_listings_of_one_book_normalize_to_the_same_key(string $a, string $b): void
    {
        $this->assertSame(normalize_title($a), normalize_title($b));
    }

    public function test_it_keeps_different_books_apart(): void
    {
        $this->assertNotSame(normalize_title('Oʻtkan kunlar'), normalize_title('Shaytanat'));
        // The digraph is a real letter: oʻt and ot are different words.
        $this->assertNotSame(normalize_title('Oʻt'), normalize_title('Ot'));
    }

    public function test_a_normalized_title_is_lowercase_latin(): void
    {
        $this->assertSame('oʻtkan kunlar', normalize_title('Ўткан кунлар (қаттиқ муқова)'));
    }

    public function test_the_fingerprint_is_stable_across_script_and_apostrophe(): void
    {
        $this->assertSame(
            book_fingerprint('Oʻtkan kunlar', 'Abdulla Qodiriy', 1926),
            book_fingerprint('Ўткан кунлар', 'Абдулла Қодирий', 1926),
        );
    }

    public function test_the_fingerprint_separates_editions_by_year(): void
    {
        $this->assertNotSame(
            book_fingerprint('Oʻtkan kunlar', 'Abdulla Qodiriy', 1926),
            book_fingerprint('Oʻtkan kunlar', 'Abdulla Qodiriy', 2019),
        );
    }

    public function test_the_fingerprint_works_without_an_author_or_year(): void
    {
        $this->assertSame(40, strlen(book_fingerprint('Oʻtkan kunlar')));
        $this->assertSame(
            book_fingerprint('Oʻtkan kunlar'),
            book_fingerprint('Ўткан кунлар'),
        );
    }
}
