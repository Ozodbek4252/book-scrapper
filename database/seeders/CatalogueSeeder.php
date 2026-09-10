<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ScrapeStage;
use App\Models\Author;
use App\Models\Book;
use App\Models\BookSource;
use App\Models\Publisher;
use App\Models\ScrapeError;
use App\Models\ScrapeRun;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Enough sample data to make the dashboard and lists worth looking at before
 * any real driver exists.
 */
class CatalogueSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $publishers = Publisher::factory()->count(4)->create();
        $authors = Author::factory()->count(8)->create();

        Book::factory()
            ->count(40)
            ->recycle($publishers)
            ->create()
            ->each(function (Book $book) use ($authors): void {
                $book->authors()->attach(
                    $authors->random(fake()->numberBetween(1, 2))
                        ->values()
                        ->mapWithKeys(fn (Author $author, int $index): array => [
                            $author->id => ['position' => $index],
                        ])
                        ->all(),
                );

                foreach (fake()->randomElements(['kitob_uz', 'asaxiy_uz'], fake()->numberBetween(1, 2)) as $sourceKey) {
                    BookSource::factory()->for($book)->forSource($sourceKey)->create();
                }
            });

        // A handful with no ISBN, which is what the fingerprint merge is for.
        Book::factory()->withoutIsbn()->count(6)->recycle($publishers)->create();

        Book::factory()->verified()->count(8)->recycle($publishers)->create();

        ScrapeRun::factory()->completed()->count(3)->create();
        ScrapeRun::factory()->running()->create();

        $failed = ScrapeRun::factory()->failed()->create();
        ScrapeError::factory()
            ->for($failed, 'run')
            ->count(4)
            ->sequence(
                ['stage' => ScrapeStage::Fetch, 'message' => 'HTTP 503 from the catalogue page'],
                ['stage' => ScrapeStage::Parse, 'message' => 'Title selector matched nothing'],
                ['stage' => ScrapeStage::Parse, 'message' => 'ISBN failed its check digit'],
                ['stage' => ScrapeStage::Upsert, 'message' => 'Two sources disagree on publisher, both locked'],
            )
            ->create();
    }
}
