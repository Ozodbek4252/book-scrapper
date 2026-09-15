<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Catalogue\UpsertBookFromSource;
use App\Enums\TrustLevel;
use App\Models\Book;
use App\Models\BookSource;
use App\Models\User;
use App\Scraping\DTO\RawBook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BookSuggestionApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create());
    }

    /**
     * @return array<string, mixed>
     */
    private function valid(array $overrides = []): array
    {
        return array_merge([
            'isbn' => '978-9943-6501-9-0',
            'title' => 'Choʻlpon: Kecha va kunduz (qattiq muqova)',
            'authors' => ['Abdulhamid Choʻlpon'],
            'publisher' => 'Академнашр',
            'published_year' => 2021,
            'pages' => 336,
        ], $overrides);
    }

    public function test_a_submitted_book_goes_through_the_same_merge_path_as_a_scrape(): void
    {
        $this->postJson('/api/v1/books/suggestions', $this->valid())->assertStatus(202);

        $book = Book::sole();

        $this->assertSame('9789943650190', $book->isbn13);
        $this->assertSame('Choʻlpon: Kecha va kunduz', $book->title, 'the noise should be stripped');
        $this->assertSame('Чўлпон: Кеча ва кундуз', $book->title_cyrillic);
        $this->assertSame(2021, $book->published_year);
    }

    public function test_it_arrives_unverified_and_least_trusted(): void
    {
        $this->postJson('/api/v1/books/suggestions', $this->valid())->assertStatus(202);

        $this->assertFalse(Book::sole()->verified, 'a submission must wait for a human');
        $this->assertSame('user_submission', BookSource::sole()->source_key);
        $this->assertSame(TrustLevel::UserSubmission, BookSource::sole()->trustLevel());
    }

    public function test_a_real_source_outranks_a_user_submission_field_by_field(): void
    {
        $this->postJson('/api/v1/books/suggestions', $this->valid(['pages' => 999]))->assertStatus(202);

        // The same book later arrives from a shop, which is trusted more.
        app(UpsertBookFromSource::class)->handle(
            new RawBook(
                sourceKey: 'asaxiy_uz',
                url: 'https://asaxiy.uz/product/x',
                externalId: 'A1',
                title: 'Choʻlpon: Kecha va kunduz',
                isbn: '9789943650190',
                pages: '336',
            ),
            TrustLevel::Bookstore,
        );

        $this->assertSame(336, Book::sole()->pages);
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function badSubmissions(): array
    {
        return [
            'no title' => [['title' => ''], 'title'],
            'title too short' => [['title' => 'x'], 'title'],
            'isbn fails its check digit' => [['isbn' => '9789943650191'], 'isbn'],
            'isbn is nonsense' => [['isbn' => 'not-an-isbn'], 'isbn'],
            'year in the future' => [['published_year' => 3000], 'published_year'],
            'year absurdly old' => [['published_year' => 900], 'published_year'],
            'negative pages' => [['pages' => -5], 'pages'],
            'too many authors' => [['authors' => array_fill(0, 11, 'A Person')], 'authors'],
            'empty author' => [['authors' => ['']], 'authors.0'],
        ];
    }

    #[DataProvider('badSubmissions')]
    public function test_it_refuses_bad_input(array $overrides, string $field): void
    {
        $this->postJson('/api/v1/books/suggestions', $this->valid($overrides))
            ->assertStatus(422)
            ->assertJsonValidationErrors($field);

        $this->assertSame(0, Book::count());
    }

    public function test_a_submission_without_an_isbn_is_allowed_and_merges_on_its_fingerprint(): void
    {
        $this->postJson('/api/v1/books/suggestions', $this->valid(['isbn' => null]))->assertStatus(202);

        $book = Book::sole();

        $this->assertNull($book->isbn13);
        $this->assertNotNull($book->fingerprint);
    }

    public function test_an_overlong_description_is_refused_rather_than_truncated(): void
    {
        $this->postJson('/api/v1/books/suggestions', $this->valid(['description' => str_repeat('a', 5001)]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('description');
    }

    public function test_a_photographed_cover_is_stored_and_kept_on_the_book(): void
    {
        Storage::fake('public');

        $this->post('/api/v1/books/suggestions', $this->valid([
            'cover' => UploadedFile::fake()->image('lol.jpg', 900, 1350),
        ]))->assertStatus(202);

        $book = Book::sole();

        $this->assertNotNull($book->cover_path, 'the photograph should reach the book');
        Storage::disk('public')->assertExists($book->cover_path);
        $this->assertStringStartsWith('covers/', $book->cover_path);
    }

    public function test_a_stored_cover_is_served_as_the_cover_url(): void
    {
        Storage::fake('public');

        $this->post('/api/v1/books/suggestions', $this->valid([
            'cover' => UploadedFile::fake()->image('lol.jpg'),
        ]))->assertStatus(202);

        $book = Book::sole();
        $this->assertNull($book->cover_url, 'nothing scraped it, so there is no remote URL');

        // A client asks one question — where is the cover — and gets one answer.
        $this->getJson('/api/v1/books/'.$book->isbn13)
            ->assertOk()
            ->assertJsonPath('data.cover_url', Storage::disk('public')->url($book->cover_path));
    }

    public function test_a_submission_without_a_cover_still_works(): void
    {
        Storage::fake('public');

        $this->post('/api/v1/books/suggestions', $this->valid())->assertStatus(202);

        $this->assertNull(Book::sole()->cover_path);
        Storage::disk('public')->assertDirectoryEmpty('/');
    }

    public function test_a_scraped_cover_url_outranks_a_reader_photograph(): void
    {
        Storage::fake('public');

        $this->post('/api/v1/books/suggestions', $this->valid([
            'cover' => UploadedFile::fake()->image('mine.jpg'),
        ]))->assertStatus(202);

        app(UpsertBookFromSource::class)->handle(
            new RawBook(
                sourceKey: 'asaxiy_uz',
                url: 'https://asaxiy.uz/product/kecha-va-kunduz',
                externalId: 'asaxiy:1',
                title: 'Choʻlpon: Kecha va kunduz',
                isbn: '9789943650190',
                coverUrl: 'https://asaxiy.uz/covers/kecha.jpg',
            ),
            TrustLevel::Bookstore,
        );

        $book = Book::sole()->refresh();

        // Both are kept: the shop's artwork is what clients see, and the
        // reader's photograph stays on file behind it.
        $this->assertSame('https://asaxiy.uz/covers/kecha.jpg', $book->cover_url);
        $this->assertNotNull($book->cover_path);
        $this->getJson('/api/v1/books/'.$book->isbn13)
            ->assertJsonPath('data.cover_url', 'https://asaxiy.uz/covers/kecha.jpg');
    }

    /**
     * @return array<string, mixed>
     */
    public static function badCovers(): array
    {
        return [
            'not an image' => [UploadedFile::fake()->create('notes.pdf', 40, 'application/pdf')],
            'too large' => [UploadedFile::fake()->image('huge.jpg')->size(5121)],
        ];
    }

    #[DataProvider('badCovers')]
    public function test_it_refuses_a_bad_cover(UploadedFile $cover): void
    {
        Storage::fake('public');

        $this->post('/api/v1/books/suggestions', $this->valid(['cover' => $cover]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('cover');

        $this->assertSame(0, Book::count());
        Storage::disk('public')->assertDirectoryEmpty('/');
    }
}
