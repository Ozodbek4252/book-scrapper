<?php

declare(strict_types=1);

namespace App\Scraping;

use DOMDocument;
use DOMXPath;

/**
 * Small helpers every driver needs for reading a saved page.
 *
 * Selectors stay in their own driver. Only the mechanics live here.
 */
final readonly class Html
{
    /**
     * Parse a page without letting libxml complain about real-world markup.
     */
    public static function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;

        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($document);
    }

    /**
     * Collapse whitespace and decode entities, the way a reader sees the text.
     */
    public static function text(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode($value)));
    }
}
