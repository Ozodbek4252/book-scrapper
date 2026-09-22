<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Scraping\Drivers\QamarUzDriver;
use App\Scraping\DTO\RawBook;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

use function App\Support\normalize_isbn;
use function App\Support\normalize_title;

/**
 * Runs entirely against a saved page. Their markup will change; this is what
 * says so, and says which field stopped arriving.
 */
class QamarUzDriverTest extends TestCase
{
    private const URL = 'https://qamar.uz/kitob/otkan-kunlar-romoni';

    protected function setUp(): void
    {
        parent::setUp();

        // Nothing here may touch the network.
        Http::preventStrayRequests();
    }

    private function driver(): QamarUzDriver
    {
        return app(QamarUzDriver::class);
    }

    private function fixture(string $name): string
    {
        return (string) file_get_contents(base_path("tests/Fixtures/qamar_uz/{$name}"));
    }

    private function book(): RawBook
    {
        $book = $this->driver()->parse(self::URL, $this->fixture('product.html'));

        $this->assertNotNull($book, 'the fixture should parse as a book');

        return $book;
    }

    public function test_it_reads_the_whole_record_off_a_saved_page(): void
    {
        $book = $this->book();

        $this->assertSame('qamar_uz', $book->sourceKey);
        $this->assertSame(self::URL, $book->url);
        $this->assertSame('otkan-kunlar-romoni', $book->externalId);
        $this->assertSame("O'tkan kunlar ro'moni", $book->title);
        $this->assertSame(['Abdulla Qodiriy'], $book->authors);
        $this->assertSame('Qamar', $book->publisher);
        $this->assertSame('9789910922725', $book->isbn);
        $this->assertSame('576', $book->pages);
    }

    public function test_it_reads_price_and_stock_from_the_structured_data(): void
    {
        $book = $this->book();

        $this->assertSame('50000', $book->price);
        $this->assertTrue($book->inStock);
        $this->assertStringContainsString('qamar.uz', (string) $book->coverUrl);
    }

    public function test_it_keeps_every_raw_value_for_re_parsing_later(): void
    {
        $payload = $this->book()->payload;

        $this->assertSame('9789910922725', $payload['details']['ISBN / shtrix-kod']);
        $this->assertSame('Qattiq', $payload['cover_type']);
        $this->assertSame('Book', $payload['json_ld']['@type']);
    }

    public function test_the_scraped_values_survive_normalization(): void
    {
        $book = $this->book();

        $this->assertSame('9789910922725', normalize_isbn($book->isbn));
        // Straight apostrophes normalize to U+02BB, the turned comma of the
        // oʻ/gʻ digraph, same as the raw title's own two apostrophes.
        $this->assertSame('oʻtkan kunlar roʻmoni', normalize_title((string) $book->title));
    }

    public function test_a_page_with_no_book_data_is_skipped_rather_than_guessed_at(): void
    {
        $notABook = '<html><body><h1>Daftar</h1></body></html>';

        $this->assertNull($this->driver()->parse('https://qamar.uz/kitob/daftar', $notABook));
    }

    public function test_an_empty_page_is_skipped(): void
    {
        $this->assertNull($this->driver()->parse(self::URL, ''));
    }

    public function test_it_reports_its_key(): void
    {
        $this->assertSame('qamar_uz', $this->driver()->key());
        $this->assertSame(QamarUzDriver::KEY, $this->driver()->key());
    }
}
