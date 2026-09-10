<?php

declare(strict_types=1);

namespace App\Support;

/**
 * ISBN parsing and validation.
 *
 * Everything here is a pure function over strings. The raw string a site or a
 * barcode scanner gave us is always kept separately; these only produce the
 * canonical form used as a merge key.
 */

/**
 * Prefixes assigned to Uzbekistan. Neither Google Books nor Open Library
 * covers these, which is the reason this project exists.
 *
 * @var array<int, string>
 */
const UZBEK_ISBN_PREFIXES = ['9789943', '9789910'];

/**
 * Strip everything that is not an ISBN character.
 *
 * The label is removed first, because "ISBN-13:" and "ISBN-10:" carry digits
 * of their own and a scraped page nearly always prints one. Without this,
 * "ISBN-13: 978-9943-01-234-9" yields "139789943012349".
 *
 * X is kept because it is a legal ISBN-10 check digit.
 */
function isbn_digits(string $raw): string
{
    $raw = (string) preg_replace('/\bISBN\s*[-–]?\s*(?:10|13)?\s*:?/iu', ' ', $raw);

    return (string) preg_replace('/[^0-9X]/', '', mb_strtoupper($raw));
}

/**
 * The check digit an ISBN-13 body of 12 digits should end with.
 */
function isbn13_check_digit(string $twelve): string
{
    $sum = 0;

    foreach (str_split($twelve) as $position => $digit) {
        $sum += (int) $digit * ($position % 2 === 0 ? 1 : 3);
    }

    return (string) ((10 - $sum % 10) % 10);
}

/**
 * The check digit an ISBN-10 body of 9 digits should end with. Can be X.
 */
function isbn10_check_digit(string $nine): string
{
    $sum = 0;

    foreach (str_split($nine) as $position => $digit) {
        $sum += (int) $digit * (10 - $position);
    }

    $check = (11 - $sum % 11) % 11;

    return $check === 10 ? 'X' : (string) $check;
}

function is_valid_isbn10(string $isbn): bool
{
    if (preg_match('/^\d{9}[\dX]$/', $isbn) !== 1) {
        return false;
    }

    return isbn10_check_digit(substr($isbn, 0, 9)) === substr($isbn, -1);
}

/**
 * A correct check digit is not enough on its own: "0000000000000" passes it.
 * Every real ISBN-13 is a Bookland EAN, so it starts 978 or 979.
 */
function is_valid_isbn13(string $isbn): bool
{
    if (preg_match('/^97[89]\d{10}$/', $isbn) !== 1) {
        return false;
    }

    return isbn13_check_digit(substr($isbn, 0, 12)) === substr($isbn, -1);
}

/**
 * Widen a valid ISBN-10 to its ISBN-13 form, or null when it is not valid.
 */
function isbn10_to_isbn13(string $isbn10): ?string
{
    if (! is_valid_isbn10($isbn10)) {
        return null;
    }

    $body = '978'.substr($isbn10, 0, 9);

    return $body.isbn13_check_digit($body);
}

/**
 * The canonical ISBN-13 for any input, or null when there is no valid ISBN in
 * it. A bad check digit is rejected here rather than stored and merged on.
 */
function normalize_isbn(?string $raw): ?string
{
    if ($raw === null) {
        return null;
    }

    $candidate = isbn_digits($raw);

    if (is_valid_isbn13($candidate)) {
        return $candidate;
    }

    return is_valid_isbn10($candidate) ? isbn10_to_isbn13($candidate) : null;
}

/**
 * Whether a canonical ISBN-13 was issued in Uzbekistan.
 */
function is_uzbek_isbn(?string $isbn13): bool
{
    if ($isbn13 === null || ! is_valid_isbn13($isbn13)) {
        return false;
    }

    foreach (UZBEK_ISBN_PREFIXES as $prefix) {
        if (str_starts_with($isbn13, $prefix)) {
            return true;
        }
    }

    return false;
}
