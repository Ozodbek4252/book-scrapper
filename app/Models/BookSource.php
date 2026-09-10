<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TrustLevel;
use Database\Factories\BookSourceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What one site knows about one book, kept exactly as it was scraped.
 */
#[Fillable([
    'book_id',
    'source_key',
    'external_id',
    'url',
    'raw_payload',
    'price',
    'in_stock',
    'scraped_at',
])]
class BookSource extends Model
{
    /** @use HasFactory<BookSourceFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Book, $this>
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    /**
     * How much this source is trusted when two sources disagree.
     */
    public function trustLevel(): TrustLevel
    {
        $level = config("scraping.sources.{$this->source_key}.trust_level");

        return $level instanceof TrustLevel
            ? $level
            : TrustLevel::tryFrom((int) $level) ?? TrustLevel::UserSubmission;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'raw_payload' => 'array',
            'price' => 'decimal:2',
            'in_stock' => 'boolean',
            'scraped_at' => 'datetime',
        ];
    }
}
