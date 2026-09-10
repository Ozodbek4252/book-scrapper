<x-layouts.app title="Books">
    <x-slot:heading>Books</x-slot:heading>
    <x-slot:subheading>{{ Number::format($books->total()) }} in the catalogue</x-slot:subheading>

    <x-slot:actions>
        <div class="flex flex-wrap items-center gap-3">
            <form method="GET" action="{{ route('books.index') }}" class="flex flex-wrap items-center gap-2">
                <input type="hidden" name="view" value="{{ $view }}">
                <label for="q" class="sr-only">Search by title or ISBN</label>
                <input
                    id="q"
                    type="search"
                    name="q"
                    value="{{ $search }}"
                    placeholder="Title in either script, or ISBN"
                    class="w-72 rounded-md border border-neutral-300 bg-white px-3 py-1.5 text-sm placeholder:text-neutral-400 focus:border-neutral-900 focus:outline-none dark:border-neutral-700 dark:bg-neutral-900 dark:focus:border-neutral-100"
                >
                <label class="flex items-center gap-2 text-sm text-neutral-600 dark:text-neutral-400">
                    <input
                        type="checkbox"
                        name="unverified"
                        value="1"
                        @checked(request()->boolean('unverified'))
                        class="rounded border-neutral-300 dark:border-neutral-700"
                    >
                    Unverified only
                </label>
                <button type="submit" class="rounded-md bg-neutral-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-neutral-700 dark:bg-neutral-100 dark:text-neutral-900 dark:hover:bg-neutral-300">
                    Search
                </button>
            </form>

            <div class="flex items-center rounded-md border border-neutral-300 p-0.5 dark:border-neutral-700">
                <a
                    href="{{ request()->fullUrlWithQuery(['view' => 'list']) }}"
                    @class([
                        'rounded px-2.5 py-1 text-sm transition',
                        'bg-neutral-900 text-white dark:bg-neutral-100 dark:text-neutral-900' => $view === 'list',
                        'text-neutral-600 hover:bg-neutral-100 dark:text-neutral-400 dark:hover:bg-neutral-800' => $view !== 'list',
                    ])
                    aria-label="List view"
                    @if ($view === 'list') aria-current="page" @endif
                >List</a>
                <a
                    href="{{ request()->fullUrlWithQuery(['view' => 'grid']) }}"
                    @class([
                        'rounded px-2.5 py-1 text-sm transition',
                        'bg-neutral-900 text-white dark:bg-neutral-100 dark:text-neutral-900' => $view === 'grid',
                        'text-neutral-600 hover:bg-neutral-100 dark:text-neutral-400 dark:hover:bg-neutral-800' => $view !== 'grid',
                    ])
                    aria-label="Grid view"
                    @if ($view === 'grid') aria-current="page" @endif
                >Grid</a>
            </div>
        </div>
    </x-slot:actions>

    @if ($books->isEmpty())
        @php($emptyMessage = $search !== ''
            ? "Nothing matches “{$search}”."
            : 'No books yet. Run a source, or load demo data with: php artisan db:seed --class=CatalogueSeeder')
        <x-card>
            <x-empty-state :message="$emptyMessage" />
        </x-card>
    @elseif ($view === 'grid')
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6">
            @foreach ($books as $book)
                <a
                    href="{{ route('books.show', $book) }}"
                    class="group overflow-hidden rounded-xl border border-neutral-200 bg-white transition hover:border-neutral-400 dark:border-neutral-800 dark:bg-neutral-900 dark:hover:border-neutral-600"
                >
                    <div class="aspect-2/3 w-full overflow-hidden bg-neutral-100 dark:bg-neutral-800">
                        @if ($book->cover_url)
                            <img
                                src="{{ $book->cover_url }}"
                                alt="Cover of {{ $book->title }}"
                                loading="lazy"
                                class="h-full w-full object-cover transition group-hover:scale-105"
                            >
                        @else
                            <div class="flex h-full w-full items-center justify-center text-3xl text-neutral-300 dark:text-neutral-700" aria-hidden="true">📚</div>
                        @endif
                    </div>
                    <div class="p-3">
                        <p class="line-clamp-2 text-sm font-medium">{{ $book->title }}</p>
                        <p class="mt-1 line-clamp-1 text-xs text-neutral-500 dark:text-neutral-400">
                            {{ $book->authors->pluck('full_name')->join(', ') ?: '—' }}
                        </p>
                    </div>
                </a>
            @endforeach
        </div>
    @else
        <x-card>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-neutral-200 text-xs uppercase tracking-wide text-neutral-500 dark:border-neutral-800 dark:text-neutral-400">
                        <tr>
                            <th scope="col" class="px-5 py-3 font-medium">Cover</th>
                            <th scope="col" class="px-5 py-3 font-medium">Title</th>
                            <th scope="col" class="px-5 py-3 font-medium">Authors</th>
                            <th scope="col" class="px-5 py-3 font-medium">Publisher</th>
                            <th scope="col" class="px-5 py-3 font-medium">Year</th>
                            <th scope="col" class="px-5 py-3 font-medium">ISBN</th>
                            <th scope="col" class="px-5 py-3 text-right font-medium">Sources</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-200 dark:divide-neutral-800">
                        @foreach ($books as $book)
                            <tr class="hover:bg-neutral-50 dark:hover:bg-neutral-800/50">
                                <td class="px-5 py-3">
                                    <div class="h-14 w-10 overflow-hidden rounded bg-neutral-100 dark:bg-neutral-800">
                                        @if ($book->cover_url)
                                            <img
                                                src="{{ $book->cover_url }}"
                                                alt="Cover of {{ $book->title }}"
                                                loading="lazy"
                                                class="h-full w-full object-cover"
                                            >
                                        @else
                                            <div class="flex h-full w-full items-center justify-center text-neutral-300 dark:text-neutral-700" aria-hidden="true">📚</div>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-5 py-3">
                                    <a href="{{ route('books.show', $book) }}" class="font-medium hover:underline">
                                        {{ $book->title }}
                                    </a>
                                    @if ($book->title_cyrillic && $book->title_cyrillic !== $book->title)
                                        <span class="block text-xs text-neutral-500 dark:text-neutral-400">{{ $book->title_cyrillic }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-neutral-600 dark:text-neutral-400">
                                    {{ $book->authors->pluck('full_name')->join(', ') ?: '—' }}
                                </td>
                                <td class="px-5 py-3 text-neutral-600 dark:text-neutral-400">
                                    {{ $book->publisher?->name ?? '—' }}
                                </td>
                                <td class="px-5 py-3 tabular-nums text-neutral-600 dark:text-neutral-400">
                                    {{ $book->published_year ?? '—' }}
                                </td>
                                <td class="px-5 py-3 font-mono text-xs text-neutral-600 dark:text-neutral-400">
                                    {{ $book->isbn13 ?? '—' }}
                                </td>
                                <td class="px-5 py-3 text-right tabular-nums text-neutral-600 dark:text-neutral-400">
                                    {{ $book->sources_count }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    @endif

    @if ($books->hasPages())
        <div class="mt-6">{{ $books->links() }}</div>
    @endif
</x-layouts.app>
