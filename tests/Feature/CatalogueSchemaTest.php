<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ScrapeRunStatus;
use App\Enums\ScrapeStage;
use App\Enums\TrustLevel;
use App\Models\Author;
use App\Models\Book;
use App\Models\BookSource;
use App\Models\Publisher;
use App\Models\ScrapeError;
use App\Models\ScrapeRun;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogueSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_book_belongs_to_a_publisher(): void
    {
        $publisher = Publisher::factory()->create(['name' => 'Akademnashr']);
        $book = Book::factory()->for($publisher)->create();

        $this->assertTrue($book->publisher->is($publisher));
        $this->assertTrue($publisher->books->contains($book));
    }

    public function test_authors_keep_the_order_the_source_listed_them_in(): void
    {
        $book = Book::factory()->create();
        $second = Author::factory()->create(['full_name' => 'Second Author']);
        $first = Author::factory()->create(['full_name' => 'First Author']);

        $book->authors()->attach([
            $second->id => ['position' => 1],
            $first->id => ['position' => 0],
        ]);

        $this->assertSame(
            ['First Author', 'Second Author'],
            $book->authors()->pluck('full_name')->all(),
        );
    }

    public function test_a_book_keeps_one_row_per_source(): void
    {
        $book = Book::factory()->create();
        BookSource::factory()->for($book)->forSource('kitob_uz')->create();
        BookSource::factory()->for($book)->forSource('asaxiy_uz')->create();

        $this->assertSame(['kitob_uz', 'asaxiy_uz'], $book->sources->pluck('source_key')->all());
    }

    public function test_the_raw_payload_survives_a_round_trip(): void
    {
        $payload = ['title' => 'Oʻtkan kunlar', 'price' => '45 000 soʻm', 'nested' => ['id' => 7]];

        $source = BookSource::factory()->create(['raw_payload' => $payload]);

        $this->assertSame($payload, $source->fresh()->raw_payload);
    }

    public function test_the_same_product_cannot_be_recorded_twice_for_one_source(): void
    {
        BookSource::factory()->create(['source_key' => 'kitob_uz', 'external_id' => '42']);

        $this->expectException(QueryException::class);

        BookSource::factory()->create(['source_key' => 'kitob_uz', 'external_id' => '42']);
    }

    public function test_two_books_cannot_share_an_isbn(): void
    {
        Book::factory()->create(['isbn13' => '9789943123456']);

        $this->expectException(QueryException::class);

        Book::factory()->create(['isbn13' => '9789943123456']);
    }

    public function test_books_without_an_isbn_are_allowed_side_by_side(): void
    {
        Book::factory()->withoutIsbn()->count(3)->create();

        $this->assertSame(3, Book::whereNull('isbn13')->count());
    }

    public function test_locked_fields_are_readable_as_a_list(): void
    {
        $book = Book::factory()->withLockedFields(['title', 'published_year'])->create();

        $this->assertTrue($book->isLocked('title'));
        $this->assertFalse($book->isLocked('pages'));
    }

    public function test_a_book_with_no_locked_fields_locks_nothing(): void
    {
        $book = Book::factory()->create();

        $this->assertNull($book->locked_fields);
        $this->assertFalse($book->isLocked('title'));
    }

    public function test_a_source_reports_the_trust_level_from_config(): void
    {
        config(['scraping.sources.a_publisher.trust_level' => TrustLevel::Publisher]);

        $source = BookSource::factory()->forSource('a_publisher')->create();

        $this->assertSame(TrustLevel::Publisher, $source->trustLevel());
        $this->assertTrue($source->trustLevel()->outranks(TrustLevel::Bookstore));
    }

    public function test_an_unregistered_source_is_trusted_least(): void
    {
        $source = BookSource::factory()->forSource('someone_we_do_not_know')->create();

        $this->assertSame(TrustLevel::UserSubmission, $source->trustLevel());
    }

    public function test_a_run_collects_its_errors(): void
    {
        $run = ScrapeRun::factory()->failed()->create();
        ScrapeError::factory()->for($run, 'run')->atStage(ScrapeStage::Parse)->create();

        $this->assertCount(1, $run->errors);
        $this->assertSame(ScrapeStage::Parse, $run->errors->first()->stage);
        $this->assertSame(ScrapeRunStatus::Failed, $run->status);
        $this->assertTrue($run->status->isFinished());
    }

    public function test_deleting_a_run_deletes_its_errors(): void
    {
        $run = ScrapeRun::factory()->create();
        ScrapeError::factory()->for($run, 'run')->count(2)->create();

        $run->delete();

        $this->assertSame(0, ScrapeError::count());
    }

    public function test_a_new_run_starts_pending(): void
    {
        $run = ScrapeRun::factory()->create();

        $this->assertSame(ScrapeRunStatus::Pending, $run->status);
        $this->assertFalse($run->status->isFinished());
    }
}
