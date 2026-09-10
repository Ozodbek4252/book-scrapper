<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\BookFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'isbn13',
    'isbn10',
    'title',
    'title_latin',
    'title_cyrillic',
    'title_normalized',
    'subtitle',
    'publisher_id',
    'published_year',
    'pages',
    'language',
    'description',
    'cover_url',
    'cover_path',
    'fingerprint',
    'verified',
    'locked_fields',
])]
class Book extends Model
{
    /** @use HasFactory<BookFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Publisher, $this>
     */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(Publisher::class);
    }

    /**
     * @return BelongsToMany<Author, $this>
     */
    public function authors(): BelongsToMany
    {
        return $this->belongsToMany(Author::class)
            ->withPivot('position')
            ->orderByPivot('position');
    }

    /**
     * @return HasMany<BookSource, $this>
     */
    public function sources(): HasMany
    {
        return $this->hasMany(BookSource::class);
    }

    /**
     * Whether a field was corrected by hand and must not be overwritten.
     */
    public function isLocked(string $field): bool
    {
        return in_array($field, $this->locked_fields ?? [], true);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published_year' => 'integer',
            'pages' => 'integer',
            'verified' => 'boolean',
            'locked_fields' => 'array',
        ];
    }
}
