<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Catalogue\ReviewBookSubmission;
use App\Catalogue\UpsertBookFromSource;
use App\Enums\SubmissionStatus;
use App\Enums\TrustLevel;
use App\Models\Book;
use App\Models\BookSubmission;
use App\Models\User;
use App\Scraping\DTO\RawBook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReviewBookSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private function review(): ReviewBookSubmission
    {
        return app(ReviewBookSubmission::class);
    }

    private function admin(): User
    {
        return User::factory()->create();
    }

    public function test_approving_a_new_book_puts_it_in_the_catalogue(): void
    {
        $submission = BookSubmission::factory()->create();

        $book = $this->review()->approve($submission, $this->admin());

        $this->assertSame(1, Book::count());
        $this->assertSame('9789943650190', $book->isbn13);
        $this->assertTrue($book->verified, 'a human approved it');
        $this->assertSame(SubmissionStatus::Approved, $submission->fresh()->status);
    }

    public function test_the_reviewers_own_corrections_are_what_get_stored(): void
    {
        $submission = BookSubmission::factory()->create();

        $book = $this->review()->approve($submission, $this->admin(), [
            'title' => 'Choʻlpon: Kecha va kunduz',
            'pages' => '512',
        ]);

        $this->assertSame('Choʻlpon: Kecha va kunduz', $book->title);
        $this->assertSame(512, $book->pages, "the reviewer's page count must win");
    }

    public function test_only_the_fields_the_reviewer_changed_are_locked(): void
    {
        $submission = BookSubmission::factory()->create();

        $book = $this->review()->approve($submission, $this->admin(), [
            'pages' => '512',
            // Passed through unchanged, so it should stay open to a better source.
            'publisher' => 'Akademnashr',
        ]);

        $this->assertContains('pages', $book->locked_fields);
        $this->assertNotContains('publisher', $book->locked_fields);
    }

    public function test_a_locked_field_survives_a_later_scrape(): void
    {
        $submission = BookSubmission::factory()->create();
        $book = $this->review()->approve($submission, $this->admin(), ['pages' => '512']);

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

        $this->assertSame(512, $book->fresh()->pages, 'a scrape must not undo a human');
    }

    public function test_approving_a_change_updates_the_book_it_was_made_against(): void
    {
        $book = Book::factory()->create(['isbn13' => '9789943650190', 'pages' => 100]);
        $submission = BookSubmission::factory()->forBook($book)->create();

        $updated = $this->review()->approve($submission, $this->admin(), ['pages' => '336']);

        $this->assertSame(1, Book::count(), 'no second book may appear');
        $this->assertTrue($updated->is($book));
        $this->assertSame(336, $book->fresh()->pages);
    }

    public function test_a_mistyped_isbn_in_an_update_cannot_move_it_onto_another_book(): void
    {
        $target = Book::factory()->create(['isbn13' => '9789943650190', 'title' => 'The real book']);
        $other = Book::factory()->create(['isbn13' => '9789943231467', 'title' => 'Someone else']);

        $submission = BookSubmission::factory()->forBook($target)->create();
        $submission->update(['payload' => array_merge($submission->payload, ['isbn' => '9789943231467'])]);

        $this->review()->approve($submission->fresh(), $this->admin());

        $this->assertSame('Someone else', $other->fresh()->title, 'the other book must be untouched');
        $this->assertSame(2, Book::count());
    }

    public function test_rejecting_leaves_the_catalogue_exactly_as_it_was(): void
    {
        $book = Book::factory()->create(['title' => 'What we already had', 'pages' => 100]);
        $submission = BookSubmission::factory()->forBook($book)->create();

        $this->review()->reject($submission, $this->admin(), 'The photograph is of a bus ticket.');

        $this->assertSame('What we already had', $book->fresh()->title);
        $this->assertSame(100, $book->fresh()->pages);
        $this->assertSame(SubmissionStatus::Rejected, $submission->fresh()->status);
        $this->assertSame('The photograph is of a bus ticket.', $submission->fresh()->review_note);
    }

    public function test_rejecting_a_new_book_adds_nothing(): void
    {
        $submission = BookSubmission::factory()->create();

        $this->review()->reject($submission, $this->admin());

        $this->assertSame(0, Book::count());
    }

    public function test_an_approved_photograph_is_published(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $path = UploadedFile::fake()->image('cover.jpg')->store('submissions', 'local');
        $submission = BookSubmission::factory()->create(['cover_path' => $path]);

        $book = $this->review()->approve($submission, $this->admin());

        Storage::disk('public')->assertExists($book->cover_path);
        Storage::disk('local')->assertMissing($path);
        $this->assertStringStartsWith('covers/', (string) $book->cover_path);
    }

    public function test_a_rejected_photograph_is_deleted_and_never_published(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $path = UploadedFile::fake()->image('rubbish.jpg')->store('submissions', 'local');
        $submission = BookSubmission::factory()->create(['cover_path' => $path]);

        $this->review()->reject($submission, $this->admin(), 'Not a book.');

        Storage::disk('local')->assertMissing($path);
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertNull($submission->fresh()->cover_path);
    }

    public function test_a_submitted_photograph_is_not_reachable_before_review(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $path = UploadedFile::fake()->image('cover.jpg')->store('submissions', 'local');
        BookSubmission::factory()->create(['cover_path' => $path]);

        // It waits on the private disk; nothing is on the public one.
        Storage::disk('local')->assertExists($path);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }
}
