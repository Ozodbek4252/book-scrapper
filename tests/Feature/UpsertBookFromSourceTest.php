<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Catalogue\UpsertBookFromSource;
use App\Enums\TrustLevel;
use App\Models\Book;
use App\Models\BookSource;
use App\Scraping\DTO\RawBook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpsertBookFromSourceTest extends TestCase
{
    use RefreshDatabase;

    private function upsert(RawBook $raw, TrustLevel $trust = TrustLevel::Bookstore): Book
    {
        return app(UpsertBookFromSource::class)->handle($raw, $trust)['book'];
    }

    private function raw(array $overrides = []): RawBook
    {
        return new RawBook(...array_merge([
            'sourceKey' => 'shop_a',
            'url' => 'https://shop-a.uz/product/otkan-kunlar',
            'externalId' => 'A1',
            'title' => "Abdulla Qodiriy: O'tkan kunlar (qattiq muqova)",
            'authors' => ['Abdulla Qodiriy'],
            'publisher' => 'Академнашр',
            'isbn' => '978-9943-6501-9-0',
            'publishedYear' => '2021-yil',
            'pages' => '336 bet',
            'language' => "O'zbekcha",
        ], $overrides));
    }

    public function test_it_normalizes_everything_on_the_way_in(): void
    {
        $book = $this->upsert($this->raw());

        $this->assertSame('9789943650190', $book->isbn13);
        $this->assertSame(2021, $book->published_year);
        $this->assertSame(336, $book->pages);
        $this->assertSame('Abdulla Qodiriy: Oʻtkan kunlar', $book->title);
        $this->assertSame('abdulla qodiriy oʻtkan kunlar', $book->title_normalized);
        $this->assertSame('Абдулла Қодирий: Ўткан кунлар', $book->title_cyrillic);
        $this->assertSame('Akademnashr', $book->publisher->name_latin);
        $this->assertSame('Abdulla Qodiriy', $book->authors->first()->full_name);
    }

    public function test_the_isbn_is_the_merge_key(): void
    {
        $this->upsert($this->raw());
        $this->upsert($this->raw([
            'sourceKey' => 'shop_b',
            'externalId' => 'B9',
            'url' => 'https://shop-b.uz/kitob/9',
            // Same book, written the other way round, in the other script.
            'title' => 'Ўткан кунлар',
            'isbn' => '9789943650190',
        ]));

        $this->assertSame(1, Book::count(), 'one ISBN must mean one book');
        $this->assertSame(2, BookSource::count(), 'each source keeps its own row');
        $this->assertSame(['shop_a', 'shop_b'], BookSource::orderBy('source_key')->pluck('source_key')->all());
    }

    public function test_a_book_without_an_isbn_merges_on_its_fingerprint(): void
    {
        $raw = $this->raw(['isbn' => null]);

        $this->upsert($raw);
        $this->upsert(new RawBook(
            sourceKey: 'shop_b',
            url: 'https://shop-b.uz/kitob/9',
            externalId: 'B9',
            title: 'Абдулла Қодирий: Ўткан кунлар',
            authors: ['Абдулла Қодирий'],
            publishedYear: '2021',
        ));

        $this->assertSame(1, Book::count());
        $this->assertNotNull(Book::sole()->fingerprint);
    }

    public function test_two_different_books_stay_apart(): void
    {
        $this->upsert($this->raw());
        $this->upsert($this->raw([
            'sourceKey' => 'shop_b',
            'externalId' => 'B2',
            'title' => 'Choʻlpon: Kecha va kunduz',
            'isbn' => '9789943231467',
        ]));

        $this->assertSame(2, Book::count());
    }

    public function test_a_publisher_outranks_a_bookstore_field_by_field(): void
    {
        config(['scraping.sources' => [
            'shop_a' => ['trust_level' => TrustLevel::Bookstore],
            'publisher_x' => ['trust_level' => TrustLevel::Publisher],
        ]]);

        $this->upsert($this->raw(['pages' => '999', 'publishedYear' => '1999']), TrustLevel::Bookstore);

        $book = $this->upsert($this->raw([
            'sourceKey' => 'publisher_x',
            'externalId' => 'P1',
            'url' => 'https://publisher-x.uz/1',
            'pages' => '336',
            'publishedYear' => '2021',
        ]), TrustLevel::Publisher);

        $this->assertSame(336, $book->pages, 'the publisher should win');
        $this->assertSame(2021, $book->published_year);
    }

    public function test_a_bookstore_cannot_overwrite_a_publisher(): void
    {
        config(['scraping.sources' => [
            'shop_a' => ['trust_level' => TrustLevel::Bookstore],
            'publisher_x' => ['trust_level' => TrustLevel::Publisher],
        ]]);

        $this->upsert($this->raw([
            'sourceKey' => 'publisher_x',
            'externalId' => 'P1',
            'url' => 'https://publisher-x.uz/1',
            'pages' => '336',
        ]), TrustLevel::Publisher);

        $book = $this->upsert($this->raw(['pages' => '999']), TrustLevel::Bookstore);

        $this->assertSame(336, $book->pages, 'a shop must not overwrite a publisher');
    }

    public function test_a_locked_field_is_never_overwritten(): void
    {
        $book = $this->upsert($this->raw());
        $book->update(['pages' => 111, 'locked_fields' => ['pages']]);

        $updated = $this->upsert($this->raw([
            'sourceKey' => 'shop_b',
            'externalId' => 'B2',
            'url' => 'https://shop-b.uz/2',
            'pages' => '999',
        ]));

        $this->assertSame(111, $updated->pages, 'a hand corrected field must survive');
    }

    public function test_the_raw_payload_is_kept_for_every_source(): void
    {
        $this->upsert($this->raw());

        $payload = BookSource::sole()->raw_payload;

        $this->assertSame('978-9943-6501-9-0', $payload['isbn'], 'the raw value, uncleaned');
        $this->assertSame('336 bet', $payload['pages']);
    }

    public function test_rescraping_one_source_updates_rather_than_duplicates(): void
    {
        $this->upsert($this->raw(['price' => '59000']));
        $book = $this->upsert($this->raw(['price' => '61000']));

        $this->assertSame(1, Book::count());
        $this->assertSame(1, BookSource::count());
        $this->assertSame('61000.00', $book->sources->first()->price);
    }

    public function test_it_reports_whether_the_book_was_new(): void
    {
        $first = app(UpsertBookFromSource::class)->handle($this->raw(), TrustLevel::Bookstore);
        $second = app(UpsertBookFromSource::class)->handle($this->raw(), TrustLevel::Bookstore);

        $this->assertTrue($first['created']);
        $this->assertFalse($second['created']);
    }

    public function test_a_multilingual_book_does_not_overflow_the_language_column(): void
    {
        // A real asaxiy listing. It is 31 characters; the column used to be 16.
        $book = $this->upsert($this->raw(['language' => "O'zb/Rus O'zbekcha Узб/Рус/Англ"]));

        $this->assertSame("O'zb/Rus O'zbekcha Узб/Рус/Англ", $book->language);
    }

    public function test_an_absurdly_long_value_is_trimmed_rather_than_losing_the_book(): void
    {
        $book = $this->upsert($this->raw([
            'title' => str_repeat('Oʻtkan kunlar ', 60),
            'language' => str_repeat('uz/', 80),
        ]));

        $this->assertSame(255, mb_strlen((string) $book->title));
        $this->assertSame(64, mb_strlen((string) $book->language));
        $this->assertNotNull($book->id, 'the book must still be stored');
    }

    public function test_the_untrimmed_value_survives_on_the_source_row(): void
    {
        $long = str_repeat('uz/', 80);

        $this->upsert($this->raw(['language' => $long]));

        $this->assertSame($long, BookSource::sole()->raw_payload['language']);
    }
}
