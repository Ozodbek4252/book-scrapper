<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\SubmissionStatus;
use App\Models\Book;
use App\Models\BookSubmission;
use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookSubmissionApiTest extends TestCase
{
    use RefreshDatabase;

    private Device $device;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');

        $this->device = Device::factory()->create();
        Sanctum::actingAs($this->device);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Choʻlpon: Kecha va kunduz',
            'authors' => ['Abdulhamid Choʻlpon'],
            'isbn' => '978-9943-6501-9-0',
            'published_year' => 2021,
        ], $overrides);
    }

    public function test_a_new_book_waits_for_review_instead_of_entering_the_catalogue(): void
    {
        $this->postJson('/api/v1/books/suggestions', $this->payload())
            ->assertStatus(202)
            ->assertJsonPath('submission.status', 'pending');

        $this->assertSame(1, BookSubmission::count());
        $this->assertSame(0, Book::count(), 'nothing may reach the catalogue unreviewed');
    }

    public function test_the_submission_is_tied_to_the_device_that_sent_it(): void
    {
        $this->postJson('/api/v1/books/suggestions', $this->payload())->assertStatus(202);

        $this->assertTrue(BookSubmission::sole()->device->is($this->device));
    }

    public function test_a_photograph_is_held_privately_until_it_is_reviewed(): void
    {
        $this->postJson('/api/v1/books/suggestions', $this->payload([
            'cover' => UploadedFile::fake()->image('cover.jpg'),
        ]))->assertStatus(202);

        $path = BookSubmission::sole()->cover_path;

        $this->assertNotNull($path);
        Storage::disk('local')->assertExists($path);
        $this->assertSame([], Storage::disk('public')->allFiles(), 'an unreviewed photo must not be public');
    }

    public function test_a_change_to_an_existing_book_also_waits_for_review(): void
    {
        $book = Book::factory()->create(['title' => 'What we already had', 'pages' => 100]);

        $this->postJson("/api/v1/books/{$book->id}/suggestions", $this->payload(['pages' => 336]))
            ->assertStatus(202)
            ->assertJsonPath('submission.status', 'pending');

        $submission = BookSubmission::sole();

        $this->assertTrue($submission->isUpdate());
        $this->assertTrue($submission->book->is($book));
        $this->assertSame('What we already had', $book->fresh()->title, 'the book must be untouched for now');
        $this->assertSame(100, $book->fresh()->pages);
    }

    public function test_an_update_to_a_book_that_does_not_exist_is_a_404(): void
    {
        $this->postJson('/api/v1/books/999999/suggestions', $this->payload())->assertNotFound();
    }

    public function test_rubbish_is_refused_before_it_ever_reaches_the_queue(): void
    {
        $this->postJson('/api/v1/books/suggestions', $this->payload(['title' => '']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('title');

        $this->postJson('/api/v1/books/suggestions', $this->payload(['isbn' => '9789943650191']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('isbn');

        $this->assertSame(0, BookSubmission::count());
    }

    public function test_a_non_image_upload_is_refused(): void
    {
        $this->postJson('/api/v1/books/suggestions', $this->payload([
            'cover' => UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf'),
        ]))->assertStatus(422)->assertJsonValidationErrors('cover');

        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_the_same_person_can_send_a_book_and_then_correct_it(): void
    {
        $this->postJson('/api/v1/books/suggestions', $this->payload())->assertStatus(202);

        $book = Book::factory()->create();

        $this->postJson("/api/v1/books/{$book->id}/suggestions", $this->payload(['pages' => 336]))
            ->assertStatus(202);

        $this->assertSame(2, BookSubmission::count());
        $this->assertSame(2, $this->device->submissions()->count());
        $this->assertSame(
            [SubmissionStatus::Pending, SubmissionStatus::Pending],
            BookSubmission::pending()->get()->pluck('status')->all(),
        );
    }
}
