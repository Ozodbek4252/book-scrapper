<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

use function App\Support\normalize_isbn;

/**
 * A book sent in by a mobile app user.
 *
 * This is the one endpoint that takes text from the public, so everything is
 * bounded and the ISBN has to survive its own check digit.
 */
class StoreBookSuggestionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'isbn' => ['nullable', 'string', 'max:32'],
            'title' => ['required', 'string', 'min:2', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'authors' => ['nullable', 'array', 'max:10'],
            'authors.*' => ['required', 'string', 'min:2', 'max:255'],
            'publisher' => ['nullable', 'string', 'max:255'],
            'published_year' => ['nullable', 'integer', 'min:1400', 'max:'.(date('Y') + 1)],
            'pages' => ['nullable', 'integer', 'min:1', 'max:20000'],
            'language' => ['nullable', 'string', 'max:64'],
            'description' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * An ISBN that fails its check digit is a mis-scan, not a new book.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $isbn = $this->string('isbn')->trim()->value();

                if ($isbn !== '' && normalize_isbn($isbn) === null) {
                    $validator->errors()->add('isbn', 'The isbn field must be a valid ISBN-10 or ISBN-13.');
                }
            },
        ];
    }
}
