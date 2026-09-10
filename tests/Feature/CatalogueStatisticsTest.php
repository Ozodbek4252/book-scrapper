<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Catalogue\CatalogueStatistics;
use App\Models\Book;
use App\Models\BookSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogueStatisticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_counts_verified_books_rather_than_casting_the_total_to_a_boolean(): void
    {
        Book::factory()->verified()->count(8)->create();
        Book::factory()->count(3)->create();

        $summary = (new CatalogueStatistics)->summary();

        $this->assertSame(8, $summary['verified']);
        $this->assertSame(3, $summary['unverified']);
    }

    public function test_it_reports_isbn_and_cover_coverage(): void
    {
        Book::factory()->count(3)->create(['cover_url' => 'https://example.uz/cover.jpg']);
        Book::factory()->withoutIsbn()->create(['cover_url' => null, 'cover_path' => null]);

        $summary = (new CatalogueStatistics)->summary();

        $this->assertSame(4, $summary['books']);
        $this->assertSame(3, $summary['with_isbn']);
        $this->assertSame(75.0, $summary['with_isbn_percent']);
        $this->assertSame(3, $summary['with_cover']);
        $this->assertSame(75.0, $summary['with_cover_percent']);
    }

    public function test_a_cover_path_counts_as_a_cover(): void
    {
        Book::factory()->create(['cover_url' => null, 'cover_path' => 'covers/one.jpg']);

        $this->assertSame(1, (new CatalogueStatistics)->summary()['with_cover']);
    }

    public function test_an_empty_catalogue_reports_zero_rather_than_dividing_by_zero(): void
    {
        $summary = (new CatalogueStatistics)->summary();

        $this->assertSame(0, $summary['books']);
        $this->assertSame(0.0, $summary['with_isbn_percent']);
        $this->assertSame(0.0, $summary['with_cover_percent']);
    }

    public function test_it_only_counts_books_added_inside_the_recent_window(): void
    {
        Book::factory()->count(2)->create();
        Book::factory()->create(['created_at' => now()->subDays(30)]);

        $this->assertSame(2, (new CatalogueStatistics)->summary()['added_recently']);
    }

    public function test_it_groups_books_by_source_busiest_first(): void
    {
        BookSource::factory()->count(3)->forSource('kitob_uz')->create();
        BookSource::factory()->forSource('asaxiy_uz')->create();

        $perSource = (new CatalogueStatistics)->booksPerSource();

        $this->assertSame(['kitob_uz' => 3, 'asaxiy_uz' => 1], $perSource->all());
    }
}
