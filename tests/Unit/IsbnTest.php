<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function App\Support\is_uzbek_isbn;
use function App\Support\is_valid_isbn10;
use function App\Support\is_valid_isbn13;
use function App\Support\isbn10_to_isbn13;
use function App\Support\isbn_digits;
use function App\Support\normalize_isbn;

class IsbnTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function messyInputs(): array
    {
        return [
            'hyphenated' => ['978-9943-01-234-9', '9789943012349'],
            'with label' => ['ISBN 978-9943-01-234-9', '9789943012349'],
            'isbn 13 label carries digits' => ['ISBN-13: 978-9943-01-234-9', '9789943012349'],
            'isbn 10 label carries digits' => ['ISBN-10: 0 306 40615 2', '0306406152'],
            'spaces' => ['978 9943 01 234 9', '9789943012349'],
            'lowercase x kept' => ['0-306-40615-x', '030640615X'],
            'nothing usable' => ['not an isbn', ''],
        ];
    }

    #[DataProvider('messyInputs')]
    public function test_it_strips_everything_that_is_not_an_isbn_character(string $raw, string $expected): void
    {
        $this->assertSame($expected, isbn_digits($raw));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function validIsbn13s(): array
    {
        return [
            'uzbek 9943' => ['9789943012349'],
            'uzbek 9910' => ['9789910123450'],
            'penguin' => ['9780141036144'],
            'oreilly' => ['9781449331818'],
        ];
    }

    #[DataProvider('validIsbn13s')]
    public function test_it_accepts_a_correct_isbn13(string $isbn): void
    {
        $this->assertTrue(is_valid_isbn13($isbn), "{$isbn} should be valid");
        $this->assertSame($isbn, normalize_isbn($isbn));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function rejects(): array
    {
        return [
            'bad check digit' => ['9789943012345'],
            'twelve digits' => ['978994301234'],
            'fourteen digits' => ['97899430123456'],
            'letters inside' => ['97899430123AB'],
            'empty' => [''],
            'right checksum but not a bookland prefix' => ['0000000000000'],
            'ean that is not a book' => ['4006381333931'],
        ];
    }

    #[DataProvider('rejects')]
    public function test_it_rejects_an_invalid_code_early(string $isbn): void
    {
        $this->assertFalse(is_valid_isbn13($isbn), "{$isbn} should be rejected");
        $this->assertNull(normalize_isbn($isbn));
    }

    public function test_it_validates_isbn10_including_the_x_check_digit(): void
    {
        $this->assertTrue(is_valid_isbn10('0306406152'));
        $this->assertTrue(is_valid_isbn10('043942089X'));
        $this->assertFalse(is_valid_isbn10('0306406153'));
        $this->assertFalse(is_valid_isbn10('030640615'));
    }

    public function test_it_widens_isbn10_to_isbn13(): void
    {
        $this->assertSame('9780306406157', isbn10_to_isbn13('0306406152'));
        $this->assertSame('9780439420891', isbn10_to_isbn13('043942089X'));
    }

    public function test_it_refuses_to_widen_an_invalid_isbn10(): void
    {
        $this->assertNull(isbn10_to_isbn13('0306406153'));
    }

    public function test_it_normalizes_any_shape_of_input_to_one_isbn13(): void
    {
        $expected = '9780306406157';

        $this->assertSame($expected, normalize_isbn('0306406152'));
        $this->assertSame($expected, normalize_isbn('0-306-40615-2'));
        $this->assertSame($expected, normalize_isbn('ISBN-10: 0 306 40615 2'));
        $this->assertSame($expected, normalize_isbn('ISBN-13: 978 0 306 40615 7'));
        $this->assertSame($expected, normalize_isbn('9780306406157'));
    }

    public function test_null_in_null_out(): void
    {
        $this->assertNull(normalize_isbn(null));
    }

    public function test_it_recognises_uzbek_prefixes(): void
    {
        $this->assertTrue(is_uzbek_isbn('9789943012349'));
        $this->assertTrue(is_uzbek_isbn('9789910123450'));
        $this->assertFalse(is_uzbek_isbn('9780141036144'));
        $this->assertFalse(is_uzbek_isbn('9789943012345'));
        $this->assertFalse(is_uzbek_isbn(null));
    }
}
