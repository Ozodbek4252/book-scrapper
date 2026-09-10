<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AuthorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'family_name',
    'given_name',
    'full_name',
    'full_name_latin',
    'full_name_cyrillic',
    'full_name_normalized',
])]
class Author extends Model
{
    /** @use HasFactory<AuthorFactory> */
    use HasFactory;

    /**
     * @return BelongsToMany<Book, $this>
     */
    public function books(): BelongsToMany
    {
        return $this->belongsToMany(Book::class)
            ->withPivot('position')
            ->orderByPivot('position');
    }
}
