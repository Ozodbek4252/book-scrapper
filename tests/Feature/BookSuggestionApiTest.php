<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Catalogue\ReviewBookSubmission;
use App\Catalogue\UpsertBookFromSource;
use App\Enums\TrustLevel;
use App\Models\Book;
use App\Models\BookSource;
use App\Models\BookSubmission;
use App\Models\Device;
use App\Models\User;
use App\Scraping\DTO\RawBook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The whole journey: an app user sends a book, a human approves it, and only
 * then does it reach the catalogue.
 */
class BookSuggestionApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
        Sanctum::actingAs(Device::factory()->create());
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

    /**
     * Send a book and have a reviewer accept it unchanged.
     */
    private function submitAndApprove(array $overrides = []): Book
    {
        $this->postJson('/api/v1/books/suggestions', $this->valid($overrides))->assertStatus(202);

        return app(ReviewBookSubmission::class)->approve(
            BookSubmission::latest('id')->sole(),
            User::factory()->create(),
        );
    }

    public function test_an_approved_book_is_normalized_the_same_way_a_scrape_is(): void
    {
        $book = $this->submitAndApprove();

        $this->assertSame('9789943650190', $book->isbn13);
        $this->assertSame('Choʻlpon: Kecha va kunduz', $book->title, 'the noise should be stripped');
        $this->assertSame('Чўлпон: Кеча ва кундуз', $book->title_cyrillic);
        $this->assertSame(2021, $book->published_year);
    }

    public function test_it_is_recorded_as_a_user_submission_at_the_lowest_trust(): void
    {
        $this->submitAndApprove();

        $this->assertSame('user_submission', BookSource::sole()->source_key);
        $this->assertSame(TrustLevel::UserSubmission, BookSource::sole()->trustLevel());
    }

    public function test_a_real_source_outranks_a_user_submission_field_by_field(): void
    {
        $this->submitAndApprove(['pages' => 999]);

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

    public function test_a_book_without_an_isbn_merges_on_its_fingerprint(): void
    {
        $book = $this->submitAndApprove(['isbn' => null]);

        $this->assertNull($book->isbn13);
        $this->assertNotNull($book->fingerprint);
    }

    public function test_an_approved_photograph_becomes_the_books_cover(): void
    {
        $book = $this->submitAndApprove(['cover' => UploadedFile::fake()->image('cover.jpg')]);

        $this->assertNotNull($book->cover_path);
        Storage::disk('public')->assertExists($book->cover_path);
    }

    public function test_a_book_without_a_photograph_still_works(): void
    {
        $book = $this->submitAndApprove();

        $this->assertNull($book->cover_path);
        $this->assertSame(1, Book::count());
    }

    public function test_a_scraped_cover_url_still_wins_over_a_reader_photograph(): void
    {
        app(UpsertBookFromSource::class)->handle(
            new RawBook(
                sourceKey: 'asaxiy_uz',
                url: 'https://asaxiy.uz/product/x',
                externalId: 'A1',
                title: 'Choʻlpon: Kecha va kunduz',
                isbn: '9789943650190',
                coverUrl: 'https://assets.asaxiy.uz/cover.jpg',
            ),
            TrustLevel::Bookstore,
        );

        $this->submitAndApprove(['cover' => UploadedFile::fake()->image('snapshot.jpg')]);

        $this->assertSame('https://assets.asaxiy.uz/cover.jpg', Book::sole()->cover_url);
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
            'overlong description' => [['description' => str_repeat('a', 5001)], 'description'],
        ];
    }

    #[DataProvider('badSubmissions')]
    public function test_rubbish_never_reaches_the_review_queue(array $overrides, string $field): void
    {
        $this->postJson('/api/v1/books/suggestions', $this->valid($overrides))
            ->assertStatus(422)
            ->assertJsonValidationErrors($field);

        $this->assertSame(0, BookSubmission::count());
        $this->assertSame(0, Book::count());
    }

    /**
     * @return array<string, array{0: UploadedFile}>
     */
    public static function badCovers(): array
    {
        return [
            'not an image' => [UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf')],
            'too large' => [UploadedFile::fake()->image('huge.jpg')->size(6000)],
        ];
    }

    #[DataProvider('badCovers')]
    public function test_it_refuses_a_bad_photograph(UploadedFile $cover): void
    {
        $this->postJson('/api/v1/books/suggestions', $this->valid(['cover' => $cover]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('cover');

        $this->assertSame(0, BookSubmission::count());
    }
}
