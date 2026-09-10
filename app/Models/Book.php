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
use Laravel\Scout\Searchable;

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
    use HasFactory, Searchable;

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
     * What the search index holds.
     *
     * Both alphabets go in for titles and authors: someone searching
     * "Ўткан кунлар" has to find a book listed as "Oʻtkan kunlar", and the
     * other way round. The normalized forms are indexed too, so an apostrophe
     * typed any of six ways still matches.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        $this->loadMissing(['authors', 'publisher']);

        return [
            'id' => $this->id,
            'isbn13' => $this->isbn13,
            'isbn10' => $this->isbn10,
            'title' => $this->title,
            'title_latin' => $this->title_latin,
            'title_cyrillic' => $this->title_cyrillic,
            'title_normalized' => $this->title_normalized,
            'authors' => $this->authors->pluck('full_name')->all(),
            'authors_latin' => $this->authors->pluck('full_name_latin')->filter()->values()->all(),
            'authors_cyrillic' => $this->authors->pluck('full_name_cyrillic')->filter()->values()->all(),
            'authors_normalized' => $this->authors->pluck('full_name_normalized')->all(),
            'publisher' => $this->publisher?->name,
            'published_year' => $this->published_year,
            'verified' => $this->verified,
        ];
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
