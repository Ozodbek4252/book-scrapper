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

                foreach (fake()->randomElements(['asaxiy_uz', 'asaxiy_uz'], fake()->numberBetween(1, 2)) as $sourceKey) {
                    BookSource::factory()->for($book)->forSource($sourceKey)->create();
                }
            });

        // A handful with no ISBN, which is what the fingerprint merge is for.
        Book::factory()->withoutIsbn()->count(6)->recycle($publishers)->create();

        Book::factory()->verified()->count(8)->recycle($publishers)->create();

        foreach (['asaxiy_uz', 'asaxiy_uz', 'akademnashr'] as $sourceKey) {
            ScrapeRun::factory()->completed()->create(['source_key' => $sourceKey]);
        }

        ScrapeRun::factory()->running()->create([
            'source_key' => 'asaxiy_uz',
            'pages_scraped' => 12,
            'items_found' => 240,
            'items_new' => 31,
            'items_updated' => 209,
        ]);

        $errors = [
            [ScrapeStage::Fetch, 'HTTP 503 from the catalogue page'],
            [ScrapeStage::Parse, 'Title selector matched nothing'],
            [ScrapeStage::Parse, 'ISBN failed its check digit'],
            [ScrapeStage::Upsert, 'Two sources disagree on publisher, both locked'],
        ];

        $failed = ScrapeRun::factory()->failed()->create([
            'source_key' => 'asaxiy_uz',
            // The counter has to match the rows, or the run list and the run
            // page report different numbers.
            'errors_count' => count($errors),
        ]);

        foreach ($errors as [$stage, $message]) {
            ScrapeError::factory()->for($failed, 'run')->create([
                'stage' => $stage,
                'message' => $message,
            ]);
        }
    }
}
