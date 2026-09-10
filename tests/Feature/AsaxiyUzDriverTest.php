<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Scraping\Drivers\AsaxiyUzDriver;
use App\Scraping\DTO\RawBook;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

use function App\Support\is_uzbek_isbn;
use function App\Support\normalize_author_name;
use function App\Support\normalize_isbn;
use function App\Support\normalize_title;
use function App\Support\to_latin;

/**
 * Runs entirely against a saved page. Their markup will change; this is what
 * says so, and says which field stopped arriving.
 */
class AsaxiyUzDriverTest extends TestCase
{
    private const URL = 'https://asaxiy.uz/product/chulpon-kecha-va-kunduz-kattik-mukova';

    protected function setUp(): void
    {
        parent::setUp();

        // Nothing here may touch the network.
        Http::preventStrayRequests();
    }

    private function driver(): AsaxiyUzDriver
    {
        return app(AsaxiyUzDriver::class);
    }

    private function fixture(string $name): string
    {
        return (string) file_get_contents(base_path("tests/Fixtures/asaxiy_uz/{$name}"));
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

        $this->assertSame('asaxiy_uz', $book->sourceKey);
        $this->assertSame(self::URL, $book->url);
        $this->assertSame('25290', $book->externalId);
        $this->assertSame("Cho'lpon: Kecha va kunduz (qattiq muqova)", $book->title);
        $this->assertSame('978-9943-6501-9-0', $book->isbn);
        $this->assertSame('336', $book->pages);
        $this->assertSame('2021', $book->publishedYear);
        $this->assertSame('Академнашр', $book->publisher);
        $this->assertSame(['Abdulhamid Cho`lpon'], $book->authors);
        $this->assertSame("O'zbekcha", $book->language);
    }

    public function test_it_reads_price_and_stock_from_the_structured_data(): void
    {
        $book = $this->book();

        $this->assertSame('58400', $book->price);
        $this->assertTrue($book->inStock);
        $this->assertStringContainsString('asaxiy.uz', (string) $book->coverUrl);
    }

    public function test_it_keeps_every_raw_value_for_re_parsing_later(): void
    {
        $payload = $this->book()->payload;

        $this->assertSame('978-9943-6501-9-0', $payload['characteristics']['ISBN']);
        $this->assertSame('Qattiq', $payload['cover_type']);
        $this->assertSame('Lotincha', $payload['script']);
        $this->assertSame('Product', $payload['json_ld']['@type']);
    }

    public function test_nothing_is_cleaned_at_this_stage(): void
    {
        $book = $this->book();

        // Raw means raw: the hyphenated ISBN, the backtick apostrophe and the
        // "(qattiq muqova)" note all survive for the normalization layer.
        $this->assertStringContainsString('-', (string) $book->isbn);
        $this->assertStringContainsString('`', $book->authors[0]);
        $this->assertStringContainsString('(qattiq muqova)', (string) $book->title);
    }

    public function test_the_scraped_values_survive_normalization(): void
    {
        $book = $this->book();

        $isbn = normalize_isbn($book->isbn);

        $this->assertSame('9789943650190', $isbn);
        $this->assertTrue(is_uzbek_isbn($isbn));
        $this->assertSame('choʻlpon kecha va kunduz', normalize_title((string) $book->title));
        $this->assertSame('abdulhamid choʻlpon', normalize_author_name($book->authors[0]));
        // The publisher is printed in Cyrillic on an otherwise Latin page.
        $this->assertSame('Akademnashr', to_latin((string) $book->publisher));
    }

    public function test_a_page_with_no_book_fields_is_skipped_rather_than_guessed_at(): void
    {
        $notABook = '<html><body><h1>Kir yuvish mashinasi</h1><div id="characteristics-content">'
            .'<table><tr><td>Rangi</td><td>Oq</td></tr></table></div></body></html>';

        $this->assertNull($this->driver()->parse('https://asaxiy.uz/product/washing-machine', $notABook));
    }

    public function test_an_empty_page_is_skipped(): void
    {
        $this->assertNull($this->driver()->parse(self::URL, ''));
    }

    public function test_it_reports_its_key(): void
    {
        $this->assertSame('asaxiy_uz', $this->driver()->key());
        $this->assertSame(AsaxiyUzDriver::KEY, $this->driver()->key());
    }
}
