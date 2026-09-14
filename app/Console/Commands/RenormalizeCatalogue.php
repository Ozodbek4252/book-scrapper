<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Catalogue\UpsertBookFromSource;
use App\Models\BookSource;
use App\Scraping\DTO\RawBook;
use App\Scraping\SourceRegistry;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Replays every stored source payload through the normalization layer.
 *
 * Never touches the network: it rebuilds each book from what was already
 * scraped, so a fix to title cleaning, author splitting or the like reaches
 * books scraped long before the fix existed.
 */
#[Signature('catalogue:renormalize')]
#[Description('Replay every stored source payload through normalization, without re-scraping. Run after a fix to title cleaning or similar so history catches up.')]
class RenormalizeCatalogue extends Command
{
    public function handle(UpsertBookFromSource $upsert, SourceRegistry $registry): int
    {
        $total = BookSource::count();

        if ($total === 0) {
            $this->info('No sources to replay.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        BookSource::query()->orderBy('id')->chunkById(500, function ($sources) use ($upsert, $registry, $bar): void {
            foreach ($sources as $source) {
                $upsert->handle(
                    RawBook::fromArray($source->raw_payload ?? []),
                    $registry->trustLevel($source->source_key),
                );

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info("Replayed {$total} sources.");

        return self::SUCCESS;
    }
}
