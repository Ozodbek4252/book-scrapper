<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\SubmissionStatus;
use App\Models\Book;
use App\Models\BookSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SubmissionReviewScreenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
        User::factory()->create();
    }

    public function test_the_queue_shows_what_is_waiting(): void
    {
        BookSubmission::factory()->create();

        $this->get(route('submissions.index'))
            ->assertOk()
            ->assertSee('Choʻlpon: Kecha va kunduz', escape: false)
            ->assertSee('new book');
    }

    public function test_the_queue_says_so_when_nothing_is_waiting(): void
    {
        $this->get(route('submissions.index'))
            ->assertOk()
            ->assertSee('Nothing is waiting.');
    }

    public function test_a_change_is_marked_as_one_and_names_the_book(): void
    {
        $book = Book::factory()->create(['title' => 'What we already had']);
        BookSubmission::factory()->forBook($book)->create();

        $this->get(route('submissions.index'))
            ->assertOk()
            ->assertSee('change')
            ->assertSee('What we already had');
    }

    public function test_the_review_screen_shows_the_current_record_beside_the_proposal(): void
    {
        $book = Book::factory()->create(['title' => 'What we already had', 'pages' => 100]);
        $submission = BookSubmission::factory()->forBook($book)->create();

        $this->get(route('submissions.show', $submission))
            ->assertOk()
            ->assertSee('What we already had')
            ->assertSee('What we hold now')
            ->assertSee('Approve')
            ->assertSee('Reject');
    }

    public function test_approving_from_the_screen_puts_the_book_in_the_catalogue(): void
    {
        $submission = BookSubmission::factory()->create();

        $this->post(route('submissions.approve', $submission), [
            'title' => 'Choʻlpon: Kecha va kunduz',
            'authors' => 'Abdulhamid Choʻlpon',
            'isbn' => '978-9943-6501-9-0',
            'published_year' => 2021,
            'pages' => 336,
        ])->assertRedirect(route('submissions.index'));

        $this->assertSame(1, Book::count());
        $this->assertSame(336, Book::sole()->pages);
        $this->assertTrue(Book::sole()->verified);
        $this->assertSame(SubmissionStatus::Approved, $submission->fresh()->status);
    }

    public function test_a_reviewers_own_edit_is_locked_against_later_scrapes(): void
    {
        $submission = BookSubmission::factory()->create();

        $this->post(route('submissions.approve', $submission), [
            'title' => 'Choʻlpon: Kecha va kunduz',
            'authors' => 'Abdulhamid Choʻlpon',
            'pages' => 999,
        ])->assertRedirect();

        $this->assertContains('pages', Book::sole()->locked_fields);
    }

    public function test_rejecting_from_the_screen_leaves_the_book_alone(): void
    {
        $book = Book::factory()->create(['title' => 'What we already had']);
        $submission = BookSubmission::factory()->forBook($book)->create();

        $this->post(route('submissions.reject', $submission), [
            'review_note' => 'The photograph is of a bus ticket.',
        ])->assertRedirect(route('submissions.index'));

        $this->assertSame('What we already had', $book->fresh()->title);
        $this->assertSame(SubmissionStatus::Rejected, $submission->fresh()->status);
    }

    public function test_a_decided_submission_cannot_be_decided_again(): void
    {
        $submission = BookSubmission::factory()->approved()->create();

        $this->post(route('submissions.approve', $submission), ['title' => 'Anything'])
            ->assertSessionHasErrors('submission');

        $this->post(route('submissions.reject', $submission))
            ->assertSessionHasErrors('submission');
    }

    public function test_approving_needs_a_title(): void
    {
        $submission = BookSubmission::factory()->create();

        $this->post(route('submissions.approve', $submission), ['title' => ''])
            ->assertSessionHasErrors('title');

        $this->assertSame(0, Book::count());
    }

    public function test_the_photograph_is_streamed_rather_than_published(): void
    {
        $path = UploadedFile::fake()->image('cover.jpg')->store('submissions', 'local');
        $submission = BookSubmission::factory()->create(['cover_path' => $path]);

        $this->get(route('submissions.cover', $submission))->assertOk();
        $this->assertSame([], Storage::disk('public')->allFiles(), 'viewing it must not publish it');
    }

    public function test_a_missing_photograph_is_a_404_not_a_crash(): void
    {
        $submission = BookSubmission::factory()->create(['cover_path' => 'submissions/gone.jpg']);

        $this->get(route('submissions.cover', $submission))->assertNotFound();
    }
}
