<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Scraping\Drivers\HilolNashrDriver;
use App\Scraping\DTO\RawBook;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

use function App\Support\normalize_isbn;

/**
 * Runs entirely against a saved page. Their markup will change; this is what
 * says so, and says which field stopped arriving.
 */
class HilolNashrDriverTest extends TestCase
{
    private const URL = 'https://hilolnashr.uz/al-quron-mushafi';

    protected function setUp(): void
    {
        parent::setUp();

        // Nothing here may touch the network.
        Http::preventStrayRequests();
    }

    private function driver(): HilolNashrDriver
    {
        return app(HilolNashrDriver::class);
    }

    private function fixture(string $name): string
    {
        return (string) file_get_contents(base_path("tests/Fixtures/hilolnashr_uz/{$name}"));
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

        $this->assertSame('hilolnashr_uz', $book->sourceKey);
        $this->assertSame(self::URL, $book->url);
        $this->assertSame('6603', $book->externalId);
        $this->assertSame('«Қуръони Карим» (AL QURAN, 17x25 см)', $book->title);
        $this->assertSame('978-9910-556-89-0', $book->isbn);
        $this->assertSame('«Hilol Nashr»', $book->publisher);
        $this->assertSame('624', $book->pages);
        $this->assertSame('2025', $book->publishedYear);
    }

    public function test_it_has_no_author_field_at_all(): void
    {
        // Not a scraping gap: schema.org only puts author on a
        // CreativeWork/Book, and this site types every page Product.
        $this->assertSame([], $this->book()->authors);
    }

    public function test_it_reads_price_and_stock_from_the_structured_data(): void
    {
        $book = $this->book();

        $this->assertSame('108000.00', $book->price);
        $this->assertTrue($book->inStock);
        $this->assertStringContainsString('hilolnashr.uz', (string) $book->coverUrl);
    }

    public function test_it_keeps_every_raw_value_for_re_parsing_later(): void
    {
        $payload = $this->book()->payload;

        $this->assertSame('Product', $payload['json_ld']['@type']);
        $this->assertStringContainsString('чарм муқова', (string) $payload['cover_type']);
    }

    public function test_the_scraped_isbn_survives_normalization(): void
    {
        $this->assertSame('9789910556890', normalize_isbn($this->book()->isbn));
    }

    public function test_a_page_with_no_isbn_is_skipped_as_not_a_book(): void
    {
        $notABook = '<html><body><script type="application/ld+json">'
            .'{"@type":"Product","name":"Attar perfume","offers":{"price":"50000"}}'
            .'</script></body></html>';

        $this->assertNull($this->driver()->parse('https://hilolnashr.uz/some-perfume', $notABook));
    }

    public function test_a_page_with_no_product_data_is_skipped(): void
    {
        $notAProduct = '<html><body><h1>Akademnashr</h1></body></html>';

        $this->assertNull($this->driver()->parse('https://hilolnashr.uz/akadem-nashr', $notAProduct));
    }

    public function test_an_empty_page_is_skipped(): void
    {
        $this->assertNull($this->driver()->parse(self::URL, ''));
    }

    public function test_it_reports_its_key(): void
    {
        $this->assertSame('hilolnashr_uz', $this->driver()->key());
        $this->assertSame(HilolNashrDriver::KEY, $this->driver()->key());
    }
}
