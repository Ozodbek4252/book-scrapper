<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BookController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->string('q')->trim()->value();

        $books = Book::query()
            ->with(['publisher:id,name', 'authors:id,full_name'])
            ->withCount('sources')
            ->when($search !== '', fn ($query) => $query->whereAny([
                'title',
                'title_latin',
                'title_cyrillic',
                'title_normalized',
                'isbn13',
                'isbn10',
            ], 'like', "%{$search}%"))
            ->when($request->boolean('unverified'), fn ($query) => $query->where('verified', false))
            // id breaks ties so pages stay stable while a scrape is writing.
            ->latest('created_at')
            ->latest('id')
            ->paginate(24)
            ->withQueryString();

        return view('books.index', [
            'books' => $books,
            'search' => $search,
        ]);
    }

    public function show(Book $book): View
    {
        $book->load([
            'publisher',
            'authors',
            'sources' => fn ($query) => $query->orderByDesc('scraped_at')->orderBy('source_key'),
        ]);

        return view('books.show', ['book' => $book]);
    }
}
