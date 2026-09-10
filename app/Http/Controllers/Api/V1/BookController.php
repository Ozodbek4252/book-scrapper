<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookResource;
use App\Models\Book;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

use function App\Support\normalize_isbn;

class BookController extends Controller
{
    /**
     * Typo-tolerant search across both alphabets.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = $request->string('q')->trim()->value();
        $perPage = min(max($request->integer('per_page', 20), 1), 100);

        $books = $query === ''
            ? Book::query()->latest('id')->paginate($perPage)
            : Book::search($query)->paginate($perPage);

        $books->load(['authors', 'publisher']);

        return BookResource::collection($books);
    }

    /**
     * Look a book up by its barcode.
     *
     * Any shape of ISBN-10 or ISBN-13 is accepted, hyphens and all, because a
     * scanner and a human type them differently.
     */
    public function show(string $isbn): BookResource|JsonResponse
    {
        $isbn13 = normalize_isbn($isbn);

        if ($isbn13 === null) {
            return response()->json([
                'message' => 'That is not a valid ISBN-10 or ISBN-13.',
            ], 422);
        }

        $book = Book::with(['authors', 'publisher'])->where('isbn13', $isbn13)->first();

        if ($book === null) {
            return response()->json([
                'message' => 'No book is known with that ISBN.',
                'isbn13' => $isbn13,
            ], 404);
        }

        return new BookResource($book);
    }
}
