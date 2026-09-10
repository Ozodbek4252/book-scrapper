<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Book;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Book
 */
class BookResource extends JsonResource
{
    /**
     * Both alphabets are always returned. A client showing an Uzbek reader a
     * book has no way to transliterate it, so the server does it once.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'isbn13' => $this->isbn13,
            'isbn10' => $this->isbn10,
            'title' => $this->title,
            'title_latin' => $this->title_latin,
            'title_cyrillic' => $this->title_cyrillic,
            'subtitle' => $this->subtitle,
            'authors' => $this->whenLoaded('authors', fn () => $this->authors->map(fn ($author) => [
                'id' => $author->id,
                'full_name' => $author->full_name,
                'full_name_latin' => $author->full_name_latin,
                'full_name_cyrillic' => $author->full_name_cyrillic,
            ])->values()),
            'publisher' => $this->whenLoaded('publisher', fn () => $this->publisher === null ? null : [
                'id' => $this->publisher->id,
                'name' => $this->publisher->name,
                'name_latin' => $this->publisher->name_latin,
                'name_cyrillic' => $this->publisher->name_cyrillic,
            ]),
            'published_year' => $this->published_year,
            'pages' => $this->pages,
            'language' => $this->language,
            'description' => $this->description,
            'cover_url' => $this->cover_url,
            'verified' => (bool) $this->verified,
        ];
    }
}
