<x-layouts.app :title="$book->title">
    <x-slot:heading>{{ $book->title }}</x-slot:heading>
    @if ($book->subtitle)
        <x-slot:subheading>{{ $book->subtitle }}</x-slot:subheading>
    @endif

    <x-slot:actions>
        <a href="{{ route('books.index', request()->query()) }}" class="text-sm text-neutral-500 hover:underline dark:text-neutral-400">← Back to books</a>
    </x-slot:actions>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-6">
            <x-card title="Canonical record">
                <dl class="grid gap-x-6 gap-y-4 px-5 py-5 sm:grid-cols-2">
                    @foreach ([
                        'Title (Latin)' => $book->title_latin,
                        'Title (Cyrillic)' => $book->title_cyrillic,
                        'Normalized' => $book->title_normalized,
                        'Authors' => $book->authors->pluck('full_name')->join(', '),
                        'Publisher' => $book->publisher?->name,
                        'Published' => $book->published_year,
                        'Pages' => $book->pages,
                        'Language' => $book->language,
                        'ISBN-13' => $book->isbn13,
                        'ISBN-10' => $book->isbn10,
                        'Fingerprint' => $book->fingerprint,
                    ] as $label => $value)
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-neutral-500 dark:text-neutral-400">{{ $label }}</dt>
                            <dd @class([
                                'mt-1 text-sm break-words',
                                'font-mono text-xs' => in_array($label, ['ISBN-13', 'ISBN-10', 'Fingerprint'], true),
                                'text-neutral-400 dark:text-neutral-600' => blank($value),
                            ])>{{ filled($value) ? $value : '—' }}</dd>
                        </div>
                    @endforeach
                </dl>

                @if ($book->description)
                    <div class="border-t border-neutral-200 px-5 py-5 dark:border-neutral-800">
                        <h3 class="text-xs uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Description</h3>
                        <p class="mt-2 text-sm leading-relaxed">{{ $book->description }}</p>
                    </div>
                @endif
            </x-card>

            <x-card title="Sources ({{ $book->sources->count() }})">
                @if ($book->sources->isEmpty())
                    <x-empty-state message="No source has claimed this book yet." />
                @else
                    <ul class="divide-y divide-neutral-200 dark:divide-neutral-800">
                        @foreach ($book->sources as $source)
                            <li class="px-5 py-4">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <span class="flex items-center gap-2 text-sm">
                                        <span class="font-mono font-medium">{{ $source->source_key }}</span>
                                        <span class="rounded-full bg-neutral-100 px-2 py-0.5 text-xs text-neutral-600 dark:bg-neutral-800 dark:text-neutral-400">
                                            {{ $source->trustLevel()->name }}
                                        </span>
                                        @if ($source->in_stock !== null)
                                            <span class="text-xs {{ $source->in_stock ? 'text-emerald-600 dark:text-emerald-400' : 'text-neutral-500 dark:text-neutral-500' }}">
                                                {{ $source->in_stock ? 'In stock' : 'Out of stock' }}
                                            </span>
                                        @endif
                                    </span>
                                    <span class="text-xs text-neutral-500 dark:text-neutral-400">
                                        @if ($source->price !== null)
                                            {{ Number::format((float) $source->price) }} soʻm ·
                                        @endif
                                        scraped {{ $source->scraped_at?->diffForHumans() ?? 'never' }}
                                    </span>
                                </div>

                                <a href="{{ $source->url }}" rel="noopener nofollow" target="_blank" class="mt-1 block truncate text-xs text-blue-600 hover:underline dark:text-blue-400">
                                    {{ $source->url }}
                                </a>

                                <details class="mt-2">
                                    <summary class="cursor-pointer text-xs text-neutral-500 hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-neutral-100">
                                        Raw payload
                                    </summary>
                                    <pre class="mt-2 max-h-64 overflow-auto rounded-lg bg-neutral-100 p-3 text-xs dark:bg-neutral-950"><code>{{ json_encode($source->raw_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</code></pre>
                                </details>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card title="Status">
                <div class="space-y-3 px-5 py-5 text-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-neutral-500 dark:text-neutral-400">Verified</span>
                        <span>{{ $book->verified ? 'Yes' : 'No' }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-neutral-500 dark:text-neutral-400">Added</span>
                        <span>{{ $book->created_at->toDateString() }}</span>
                    </div>
                    <div>
                        <span class="text-neutral-500 dark:text-neutral-400">Locked fields</span>
                        <p class="mt-1">
                            @forelse ($book->locked_fields ?? [] as $field)
                                <span class="mr-1 inline-block rounded bg-amber-100 px-1.5 py-0.5 font-mono text-xs text-amber-900 dark:bg-amber-950 dark:text-amber-300">{{ $field }}</span>
                            @empty
                                <span class="text-neutral-400 dark:text-neutral-600">None</span>
                            @endforelse
                        </p>
                    </div>
                </div>
            </x-card>

            @if ($book->resolved_cover_url)
                <x-card title="Cover">
                    <img src="{{ $book->resolved_cover_url }}" alt="Cover of {{ $book->title }}" class="w-full" loading="lazy">
                </x-card>
            @endif
        </div>
    </div>
</x-layouts.app>
