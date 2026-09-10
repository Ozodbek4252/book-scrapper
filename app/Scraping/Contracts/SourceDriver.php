<?php

declare(strict_types=1);

namespace App\Scraping\Contracts;

use App\Scraping\DTO\RawBook;

/**
 * One site the catalogue is built from.
 *
 * Adding a source means writing one class against this contract and adding one
 * entry to config/scraping.php. Every selector or endpoint path that site needs
 * lives inside its own driver as a class constant, so a broken parser is a
 * one-line fix in one file.
 */
interface SourceDriver
{
    /**
     * The key this driver is registered under in config/scraping.php.
     */
    public function key(): string;

    /**
     * Yield product page URLs for this source.
     *
     * Lazy on purpose: a full catalogue walk should never be held in memory,
     * and the caller throttles between items.
     *
     * @return iterable<int, string>
     */
    public function discover(): iterable;

    /**
     * Read one product page into raw, uncleaned strings.
     *
     * Returns null when the page exists but holds no book, so the caller can
     * skip it without treating it as an error.
     */
    public function fetch(string $url): ?RawBook;
}
