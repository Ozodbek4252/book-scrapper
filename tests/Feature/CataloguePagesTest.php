<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ScrapeStage;
use App\Models\Author;
use App\Models\Book;
use App\Models\BookSource;
use App\Models\BookSubmission;
use App\Models\Publisher;
use App\Models\ScrapeError;
use App\Models\ScrapeRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CataloguePagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_dashboard_shows_the_catalogue_size(): void
    {
        Book::factory()->count(3)->create();

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Dashboard')
            ->assertSee('Sources');
    }

    public function test_the_dashboard_works_on_an_empty_catalogue(): void
    {
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('No scrape has run yet.');
    }

    public function test_the_dashboard_flags_submissions_waiting_for_review(): void
    {
        BookSubmission::factory()->count(2)->create();

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('2 submissions from the app waiting for review.');
    }

    public function test_the_dashboard_says_nothing_when_the_review_queue_is_empty(): void
    {
        BookSubmission::factory()->approved()->create();

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('waiting for review');
    }

    public function test_the_book_list_shows_titles_in_both_scripts(): void
    {
        Book::factory()->create([
            'title' => 'Oʻtkan kunlar',
            'title_cyrillic' => 'Ўткан кунлар',
        ]);

        $this->get(route('books.index'))
            ->assertOk()
            ->assertSee('Oʻtkan kunlar', escape: false)
            ->assertSee('Ўткан кунлар', escape: false);
    }

    public function test_the_book_list_searches_the_cyrillic_title(): void
    {
        $match = Book::factory()->create(['title' => 'Latin one', 'title_cyrillic' => 'Ўткан кунлар']);
        $other = Book::factory()->create(['title' => 'Shaytanat', 'title_cyrillic' => 'Шайтанат']);

        $this->get(route('books.index', ['q' => 'Ўткан']))
            ->assertOk()
            ->assertSee($match->title)
            ->assertDontSee($other->title);
    }

    public function test_the_book_list_searches_by_isbn(): void
    {
        $book = Book::factory()->create(['isbn13' => '9789943123456']);
        Book::factory()->create();

        $this->get(route('books.index', ['q' => '9789943123456']))
            ->assertOk()
            ->assertSee('9789943123456');
    }

    public function test_the_book_list_can_filter_to_unverified_books(): void
    {
        $unverified = Book::factory()->create(['title' => 'Needs review']);
        $verified = Book::factory()->verified()->create(['title' => 'Already checked']);

        $this->get(route('books.index', ['unverified' => 1]))
            ->assertOk()
            ->assertSee('Needs review')
            ->assertDontSee('Already checked');
    }

    public function test_the_book_list_can_filter_by_source(): void
    {
        $fromAsaxiy = Book::factory()->has(BookSource::factory()->forSource('asaxiy_uz'), 'sources')->create(['title' => 'From asaxiy']);
        $fromSubmission = Book::factory()->has(BookSource::factory()->forSource('user_submission'), 'sources')->create(['title' => 'From a reader']);

        $this->get(route('books.index', ['source' => 'asaxiy_uz']))
            ->assertOk()
            ->assertSee('From asaxiy')
            ->assertDontSee('From a reader');
    }

    public function test_the_book_list_can_filter_to_books_missing_an_isbn(): void
    {
        $withoutIsbn = Book::factory()->withoutIsbn()->create(['title' => 'No ISBN yet']);
        $withIsbn = Book::factory()->create(['title' => 'Fully catalogued', 'isbn13' => '9789943123456']);

        $this->get(route('books.index', ['missing_isbn' => 1]))
            ->assertOk()
            ->assertSee('No ISBN yet')
            ->assertDontSee('Fully catalogued');
    }

    public function test_the_book_list_can_filter_to_books_missing_a_cover(): void
    {
        $withoutCover = Book::factory()->create(['title' => 'Bare title', 'cover_url' => null, 'cover_path' => null]);
        $withCover = Book::factory()->create(['title' => 'Has a cover', 'cover_url' => 'https://example.uz/cover.jpg']);

        $this->get(route('books.index', ['missing_cover' => 1]))
            ->assertOk()
            ->assertSee('Bare title')
            ->assertDontSee('Has a cover');
    }

    public function test_the_book_list_can_filter_by_published_year_range(): void
    {
        $inRange = Book::factory()->create(['title' => 'Published in range', 'published_year' => 2015]);
        $tooEarly = Book::factory()->create(['title' => 'Too early', 'published_year' => 1990]);
        $tooLate = Book::factory()->create(['title' => 'Too late', 'published_year' => 2024]);

        $this->get(route('books.index', ['year_from' => 2000, 'year_to' => 2020]))
            ->assertOk()
            ->assertSee('Published in range')
            ->assertDontSee('Too early')
            ->assertDontSee('Too late');
    }

    public function test_the_book_list_can_filter_by_publisher_name(): void
    {
        $match = Book::factory()->for(Publisher::factory()->create(['name' => 'Akademnashr']))->create(['title' => 'From akademnashr']);
        $other = Book::factory()->for(Publisher::factory()->create(['name' => 'Kalibr Books']))->create(['title' => 'From kalibr']);

        $this->get(route('books.index', ['publisher' => 'akadem']))
            ->assertOk()
            ->assertSee('From akademnashr')
            ->assertDontSee('From kalibr');
    }

    public function test_the_empty_book_list_renders_its_message_cleanly(): void
    {
        // A truncated message means the Blade attribute quoting broke again.
        $this->get(route('books.index'))
            ->assertOk()
            ->assertSee('No books yet. Run a source, or load demo data with: php artisan db:seed --class=CatalogueSeeder');
    }

    public function test_the_book_list_says_so_when_a_search_matches_nothing(): void
    {
        Book::factory()->create();

        $this->get(route('books.index', ['q' => 'nothing matches this']))
            ->assertOk()
            ->assertSee('Nothing matches');
    }

    public function test_a_book_page_shows_its_authors_publisher_and_sources(): void
    {
        $publisher = Publisher::factory()->create(['name' => 'Akademnashr']);
        $book = Book::factory()->for($publisher)->create();
        $author = Author::factory()->create(['full_name' => 'Abdulla Qodiriy']);
        $book->authors()->attach($author, ['position' => 0]);
        BookSource::factory()->for($book)->forSource('asaxiy_uz')->create();

        $this->get(route('books.show', $book))
            ->assertOk()
            ->assertSee('Abdulla Qodiriy')
            ->assertSee('Akademnashr')
            ->assertSee('asaxiy_uz');
    }

    public function test_a_book_page_renders_the_raw_payload(): void
    {
        $book = Book::factory()->create();
        BookSource::factory()->for($book)->create(['raw_payload' => ['title' => 'Ўткан кунлар']]);

        $this->get(route('books.show', $book))
            ->assertOk()
            ->assertSee('Raw payload')
            ->assertSee('Ўткан кунлар', escape: false);
    }

    public function test_a_book_page_renders_without_a_publisher_or_sources(): void
    {
        $book = Book::factory()->create(['publisher_id' => null]);

        $this->get(route('books.show', $book))
            ->assertOk()
            ->assertSee('No source has claimed this book yet.');
    }

    public function test_an_unknown_book_is_a_404(): void
    {
        $this->get(route('books.show', 999))->assertNotFound();
    }

    public function test_the_run_list_shows_counters_and_status(): void
    {
        ScrapeRun::factory()->completed()->create(['source_key' => 'asaxiy_uz']);

        $this->get(route('scrape-runs.index'))
            ->assertOk()
            ->assertSee('asaxiy_uz')
            ->assertSee('Completed');
    }

    public function test_a_run_page_lists_its_errors(): void
    {
        $run = ScrapeRun::factory()->failed()->create();
        ScrapeError::factory()->for($run, 'run')->atStage(ScrapeStage::Parse)->create([
            'message' => 'Title selector matched nothing',
        ]);

        $this->get(route('scrape-runs.show', $run))
            ->assertOk()
            ->assertSee('Title selector matched nothing')
            ->assertSee('parse');
    }

    public function test_a_run_page_says_so_when_there_are_no_errors(): void
    {
        $run = ScrapeRun::factory()->completed()->create();

        $this->get(route('scrape-runs.show', $run))
            ->assertOk()
            ->assertSee('This run logged no errors.');
    }
}
