<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Uzbek text normalization.
 *
 * The same book is sold as "Oʻtkan kunlar" on one site and "Ўткан кунлар" on
 * another, and the apostrophe in the Latin form is written at least six
 * different ways. Without this layer those become separate records, so every
 * function here is pure and covered by unit tests.
 */

/**
 * Every character seen standing in for the Uzbek apostrophe. They all collapse
 * to U+02BB, which is the correct one for oʻ and gʻ.
 *
 * @var array<int, string>
 */
const APOSTROPHE_VARIANTS = [
    "'",        // U+0027 apostrophe
    '`',        // U+0060 grave accent
    '´',        // U+00B4 acute accent
    'ʹ',        // U+02B9 modifier letter prime
    'ʻ',        // U+02BB modifier letter turned comma (the canonical one)
    'ʼ',        // U+02BC modifier letter apostrophe
    'ʽ',        // U+02BD modifier letter reversed comma
    '‘',        // U+2018 left single quotation mark
    '’',        // U+2019 right single quotation mark
    '‛',        // U+201B single high-reversed-9 quotation mark
    '′',        // U+2032 prime
    'ꞌ',        // U+A78C latin small letter saltillo
];

/**
 * The turned comma that makes the digraphs oʻ and gʻ. U+02BB.
 */
const APOSTROPHE = 'ʻ';

/**
 * The tutuq belgisi, the glottal stop in aʼzam and sheʼr. U+02BC.
 */
const TUTUQ = 'ʼ';

/**
 * Collapse every apostrophe variant, then pick the right one for the position.
 *
 * Whichever of the six-odd characters a site typed, the output is the same, so
 * naive matching stops producing duplicates. The choice between the two real
 * apostrophes is decided by what precedes it: after o or g it is the turned
 * comma of a digraph, anywhere else it is the tutuq belgisi.
 */
function normalize_apostrophes(string $text): string
{
    $text = str_replace(APOSTROPHE_VARIANTS, APOSTROPHE, $text);

    return (string) preg_replace_callback(
        '/([ogOG])?'.APOSTROPHE.'/u',
        fn (array $match): string => ($match[1] ?? '') === ''
            ? TUTUQ
            : $match[1].APOSTROPHE,
        $text,
    );
}

/**
 * Uppercase the first character of a multibyte string.
 */
function upper_first(string $text): string
{
    return mb_strtoupper(mb_substr($text, 0, 1)).mb_substr($text, 1);
}

/**
 * Transliterate word by word, restoring the original casing afterwards.
 *
 * Casing cannot be folded into the lookup table: ш uppercases to Ш and
 * titlecases to Ш too, so one key would have to mean both "sh" and "SH".
 * Converting the lowercase word and re-casing the result avoids that.
 *
 * @param  array<string, string>  $map  lowercase source => lowercase target
 */
function transliterate_words(string $text, array $map): string
{
    return (string) preg_replace_callback(
        '/\p{L}[\p{L}'.APOSTROPHE.TUTUQ.']*/u',
        function (array $match) use ($map): string {
            $word = $match[0];
            $lower = mb_strtolower($word);
            $converted = strtr($lower, $map);

            if ($word === $lower) {
                return $converted;
            }

            if ($word === mb_strtoupper($word)) {
                return mb_strtoupper($converted);
            }

            if ($word === upper_first($lower)) {
                return upper_first($converted);
            }

            // Mixed caps inside a word: convert what we can, keep it readable.
            return upper_first($converted);
        },
        $text,
    );
}

/**
 * Latin to Cyrillic, digraphs first.
 *
 * strtr() always prefers the longest matching key, which is what makes oʻ, gʻ,
 * sh, ch and yo win over their single letters.
 *
 * @return array<string, string>
 */
function latin_to_cyrillic_map(): array
{
    return [
        'o'.APOSTROPHE => 'ў',
        'g'.APOSTROPHE => 'ғ',
        'sh' => 'ш',
        'ch' => 'ч',
        'yo' => 'ё',
        'yu' => 'ю',
        'ya' => 'я',
        'ye' => 'е',
        'ts' => 'ц',
        'a' => 'а', 'b' => 'б', 'd' => 'д', 'e' => 'е', 'f' => 'ф',
        'g' => 'г', 'h' => 'ҳ', 'i' => 'и', 'j' => 'ж', 'k' => 'к',
        'l' => 'л', 'm' => 'м', 'n' => 'н', 'o' => 'о', 'p' => 'п',
        'q' => 'қ', 'r' => 'р', 's' => 'с', 't' => 'т', 'u' => 'у',
        'v' => 'в', 'x' => 'х', 'y' => 'й', 'z' => 'з',
        TUTUQ => 'ъ',
    ];
}

/**
 * @return array<string, string>
 */
function cyrillic_to_latin_map(): array
{
    return [
        'ў' => 'o'.APOSTROPHE,
        'ғ' => 'g'.APOSTROPHE,
        'ш' => 'sh', 'ч' => 'ch', 'ё' => 'yo', 'ю' => 'yu', 'я' => 'ya',
        'ц' => 'ts', 'щ' => 'sh',
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd',
        'е' => 'e', 'ж' => 'j', 'з' => 'z', 'и' => 'i', 'й' => 'y',
        'к' => 'k', 'қ' => 'q', 'л' => 'l', 'м' => 'm', 'н' => 'n',
        'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't',
        'у' => 'u', 'ф' => 'f', 'х' => 'x', 'ҳ' => 'h', 'ъ' => TUTUQ,
        'ь' => '', 'э' => 'e', 'ы' => 'i',
    ];
}

/**
 * Transliterate Uzbek Latin into Cyrillic.
 */
function latin_to_cyrillic(string $text): string
{
    $text = normalize_apostrophes($text);

    // A word starting with e takes э in Cyrillic; inside a word it is е.
    $text = (string) preg_replace_callback(
        '/(?<![\p{L}\p{N}])(e)/iu',
        fn (array $match): string => $match[1] === 'E' ? 'Э' : 'э',
        $text,
    );

    return transliterate_words($text, latin_to_cyrillic_map());
}

/**
 * Transliterate Uzbek Cyrillic into Latin.
 */
function cyrillic_to_latin(string $text): string
{
    // A word starting with е is written ye in Latin; inside a word it is e.
    $text = (string) preg_replace_callback(
        '/(?<![\p{L}\p{N}])(е)/iu',
        fn (array $match): string => $match[1] === 'Е' ? 'Ye' : 'ye',
        $text,
    );

    return transliterate_words($text, cyrillic_to_latin_map());
}

/**
 * Which alphabet a piece of text is written in.
 *
 * @return 'cyrillic'|'latin'|'unknown'
 */
function detect_script(string $text): string
{
    $cyrillic = preg_match_all('/\p{Cyrillic}/u', $text);
    $latin = preg_match_all('/\p{Latin}/u', $text);

    if ($cyrillic === 0 && $latin === 0) {
        return 'unknown';
    }

    return $cyrillic > $latin ? 'cyrillic' : 'latin';
}

/**
 * The Latin form of a title, whichever alphabet it arrived in.
 */
function to_latin(string $text): string
{
    return detect_script($text) === 'cyrillic'
        ? cyrillic_to_latin($text)
        : normalize_apostrophes($text);
}

/**
 * The Cyrillic form of a title, whichever alphabet it arrived in.
 */
function to_cyrillic(string $text): string
{
    return detect_script($text) === 'cyrillic' ? $text : latin_to_cyrillic($text);
}

/**
 * Phrases publishers append to a title that are not part of the title.
 *
 * Both alphabets, because the same shop mixes them. Matched after apostrophes
 * are collapsed, so "to'ldirilgan" and "toʻldirilgan" both hit.
 *
 * @var array<int, string>
 */
const TITLE_NOISE_PATTERNS = [
    // "(qattiq muqova)", "(yumshoq muqova)", "(қаттиқ муқова)"
    '/\([^()]*\b(?:muqova|муқова)\b[^()]*\)/iu',
    // "2-nashr", "3 - nashr", "2-нашр"
    '/\b\d+\s*[-–]\s*(?:nashr|нашр)\b/iu',
    // "toʻldirilgan nashr", "qayta nashr", "тўлдирилган нашр"
    '/\b(?:to'.APOSTROPHE.'ldirilgan|qayta|тўлдирилган|қайта)\s+(?:nashr|нашр)\b/iu',
    // Series and edition notes publishers put in square brackets.
    '/\[[^\]]*\]/u',
];

/**
 * Drop the packaging and edition notes shops append to a title.
 */
function strip_title_noise(string $title): string
{
    $title = normalize_apostrophes($title);

    foreach (TITLE_NOISE_PATTERNS as $pattern) {
        $title = (string) preg_replace($pattern, ' ', $title);
    }

    // Tidy up what removal left behind: doubled spaces and dangling separators.
    // The trim has to be a regex: trim() works on bytes, and the byte 0x80 in
    // an en dash is also the tail of Cyrillic р, so a charlist trim chops real
    // letters in half and leaves invalid UTF-8 behind.
    $title = (string) preg_replace('/\s+/u', ' ', $title);

    return (string) preg_replace('/^[\s\-–—,;:.]+|[\s\-–—,;:.]+$/u', '', $title);
}

/**
 * The matching key for a title.
 *
 * Deliberately transliterated to Latin first, so a book listed in Cyrillic on
 * one site and Latin on another produces one key and merges into one record.
 */
function normalize_title(string $title): string
{
    $title = to_latin(strip_title_noise($title));
    $title = mb_strtolower($title);

    // Keep letters, digits and the apostrophe: it distinguishes oʻ from o.
    $title = (string) preg_replace('/[^\p{L}\p{N}'.APOSTROPHE.TUTUQ.']+/u', ' ', $title);

    return trim((string) preg_replace('/\s+/u', ' ', $title));
}

/**
 * The matching key for a person.
 *
 * Name parts are sorted, so "Abdulla Qodiriy" and "Qodiriy Abdulla" produce
 * the same key. Cyrillic spellings are transliterated first.
 */
function normalize_author_name(string $name): string
{
    $name = mb_strtolower(to_latin($name));
    $name = (string) preg_replace('/[^\p{L}\p{N}'.APOSTROPHE.TUTUQ.']+/u', ' ', $name);

    $parts = array_filter(explode(' ', trim($name)), fn (string $part): bool => $part !== '');
    sort($parts);

    return implode(' ', $parts);
}

/**
 * Whether two names describe the same person.
 *
 * Handles either name order, either alphabet, and an initial standing in for a
 * full given name. "A. Qodiriy" therefore also matches "Anvar Qodiriy": an
 * initial cannot tell two people with the same family name apart.
 */
function author_names_match(string $a, string $b): bool
{
    $left = explode(' ', normalize_author_name($a));
    $right = explode(' ', normalize_author_name($b));

    if ($left === $right) {
        return true;
    }

    if (count($left) !== count($right)) {
        return false;
    }

    foreach ($left as $index => $part) {
        $other = $right[$index];

        if ($part === $other) {
            continue;
        }

        $initial = mb_strlen($part) === 1 || mb_strlen($other) === 1;

        if (! $initial || mb_substr($part, 0, 1) !== mb_substr($other, 0, 1)) {
            return false;
        }
    }

    return true;
}

/**
 * Split a name into its parts when the source makes them detectable.
 *
 * "Qodiriy, Abdulla" is unambiguous. "Abdulla Qodiriy" is read as given then
 * family, which is how Uzbek book covers print it. A single word is left
 * unsplit rather than guessed at.
 *
 * @return array{given: ?string, family: ?string}
 */
function split_author_name(string $name): array
{
    $name = trim((string) preg_replace('/\s+/u', ' ', normalize_apostrophes($name)));

    if ($name === '') {
        return ['given' => null, 'family' => null];
    }

    if (str_contains($name, ',')) {
        [$family, $given] = array_pad(array_map('trim', explode(',', $name, 2)), 2, '');

        return [
            'given' => $given === '' ? null : $given,
            'family' => $family === '' ? null : $family,
        ];
    }

    $parts = explode(' ', $name);

    if (count($parts) < 2) {
        return ['given' => null, 'family' => null];
    }

    $family = array_pop($parts);

    return ['given' => implode(' ', $parts), 'family' => $family];
}

/**
 * The merge key for a book with no usable ISBN.
 *
 * @see normalize_isbn() for the key used when there is one.
 */
function book_fingerprint(string $title, ?string $firstAuthor = null, ?int $year = null): string
{
    return sha1(implode('|', [
        normalize_title($title),
        $firstAuthor === null ? '' : normalize_author_name($firstAuthor),
        $year === null ? '' : (string) $year,
    ]));
}
