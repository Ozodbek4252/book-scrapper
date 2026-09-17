<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Book;
use App\Scraping\SourceRegistry;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BookController extends Controller
{
    public function index(Request $request, SourceRegistry $registry): View
    {
        $search = $request->string('q')->trim()->value();
        $view = $request->string('view')->value() === 'grid' ? 'grid' : 'list';
        $source = $request->string('source')->trim()->value();
        $publisher = $request->string('publisher')->trim()->value();
        $yearFrom = $request->integer('year_from') ?: null;
        $yearTo = $request->integer('year_to') ?: null;

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
            ->when($source !== '', fn ($query) => $query->whereHas(
                'sources',
                fn ($sources) => $sources->where('source_key', $source),
            ))
            ->when($request->boolean('missing_isbn'), fn ($query) => $query->whereNull('isbn13'))
            ->when($request->boolean('missing_cover'), fn ($query) => $query->whereNull('cover_url')->whereNull('cover_path'))
            ->when($yearFrom !== null, fn ($query) => $query->where('published_year', '>=', $yearFrom))
            ->when($yearTo !== null, fn ($query) => $query->where('published_year', '<=', $yearTo))
            ->when($publisher !== '', fn ($query) => $query->whereHas(
                'publisher',
                fn ($publishers) => $publishers->where('name', 'like', "%{$publisher}%"),
            ))
            // id breaks ties so pages stay stable while a scrape is writing.
            ->latest('created_at')
            ->latest('id')
            ->paginate(24)
            ->withQueryString();

        return view('books.index', [
            'books' => $books,
            'search' => $search,
            'view' => $view,
            'source' => $source,
            'publisher' => $publisher,
            'yearFrom' => $yearFrom,
            'yearTo' => $yearTo,
            'sourceKeys' => $registry->keys(),
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
