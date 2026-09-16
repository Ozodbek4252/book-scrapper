<x-layouts.app :title="'Submission '.$submission->id">
    <x-slot:heading>{{ $proposed->title ?: '(no title)' }}</x-slot:heading>
    <x-slot:subheading>
        {{ $submission->isUpdate() ? 'A proposed change to a book we hold' : 'A book the catalogue does not have' }}
        · sent {{ $submission->created_at->diffForHumans() }}
    </x-slot:subheading>

    <x-slot:actions>
        <a href="{{ route('submissions.index') }}" class="text-sm text-neutral-500 hover:underline dark:text-neutral-400">← Back to submissions</a>
    </x-slot:actions>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6">
            <x-card title="Photograph">
                @if ($submission->cover_path)
                    <div class="p-5">
                        <img
                            src="{{ route('submissions.cover', $submission) }}"
                            alt="Submitted photograph"
                            class="w-full rounded-lg border border-neutral-200 dark:border-neutral-800"
                        >
                        <p class="mt-2 text-xs text-neutral-500 dark:text-neutral-400">
                            Held privately. It is published only if you approve.
                        </p>
                    </div>
                @else
                    <x-empty-state message="No photograph was sent." />
                @endif
            </x-card>

            @if ($submission->isUpdate() && $submission->book)
                <x-card title="What we hold now">
                    <dl class="space-y-3 px-5 py-5 text-sm">
                        @foreach ([
                            'Title' => $submission->book->title,
                            'Authors' => $submission->book->authors->pluck('full_name')->join(', '),
                            'Publisher' => $submission->book->publisher?->name,
                            'ISBN' => $submission->book->isbn13,
                            'Year' => $submission->book->published_year,
                            'Pages' => $submission->book->pages,
                        ] as $label => $value)
                            <div>
                                <dt class="text-xs uppercase tracking-wide text-neutral-500 dark:text-neutral-400">{{ $label }}</dt>
                                <dd class="mt-0.5 {{ blank($value) ? 'text-neutral-400 dark:text-neutral-600' : '' }}">{{ filled($value) ? $value : '—' }}</dd>
                            </div>
                        @endforeach
                    </dl>
                    <p class="border-t border-neutral-200 px-5 py-3 text-xs text-neutral-500 dark:border-neutral-800 dark:text-neutral-400">
                        Reject and this stays exactly as it is.
                    </p>
                </x-card>
            @endif

            <x-card title="Who sent it">
                <dl class="space-y-3 px-5 py-5 text-sm">
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Device</dt>
                        <dd class="mt-0.5 font-mono text-xs break-all">{{ $submission->device?->uuid ?? 'unknown' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Platform</dt>
                        <dd class="mt-0.5">{{ $submission->device?->platform ?? '—' }}</dd>
                    </div>
                </dl>
            </x-card>
        </div>

        <div class="lg:col-span-2">
            <x-card title="What they propose — edit anything before approving">
                @if (! $submission->status->isOpen())
                    <div class="border-b border-neutral-200 bg-neutral-50 px-5 py-3 text-sm dark:border-neutral-800 dark:bg-neutral-800/50">
                        Already {{ $submission->status->value }}
                        @if ($submission->reviewed_at) on {{ $submission->reviewed_at->toDateTimeString() }} @endif
                        @if ($submission->review_note) — “{{ $submission->review_note }}” @endif
                    </div>
                @endif

                <form method="POST" action="{{ route('submissions.approve', $submission) }}" class="space-y-4 px-5 py-5">
                    @csrf

                    @foreach ([
                        ['title', 'Title', $proposed->title, 'text'],
                        ['authors', 'Authors (comma separated)', implode(', ', $proposed->authors), 'text'],
                        ['publisher', 'Publisher', $proposed->publisher, 'text'],
                        ['isbn', 'ISBN', $proposed->isbn, 'text'],
                        ['published_year', 'Year', $proposed->publishedYear, 'number'],
                        ['pages', 'Pages', $proposed->pages, 'number'],
                        ['language', 'Language', $proposed->language, 'text'],
                    ] as [$name, $label, $value, $type])
                        <div>
                            <label for="{{ $name }}" class="block text-xs uppercase tracking-wide text-neutral-500 dark:text-neutral-400">{{ $label }}</label>
                            <input
                                id="{{ $name }}"
                                type="{{ $type }}"
                                name="{{ $name }}"
                                value="{{ old($name, $value) }}"
                                @disabled(! $submission->status->isOpen())
                                class="mt-1 w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm focus:border-neutral-900 focus:outline-none disabled:opacity-50 dark:border-neutral-700 dark:bg-neutral-900 dark:focus:border-neutral-100"
                            >
                            @error($name)<p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror
                        </div>
                    @endforeach

                    <div>
                        <label for="description" class="block text-xs uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Description</label>
                        <textarea
                            id="description"
                            name="description"
                            rows="4"
                            @disabled(! $submission->status->isOpen())
                            class="mt-1 w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm focus:border-neutral-900 focus:outline-none disabled:opacity-50 dark:border-neutral-700 dark:bg-neutral-900 dark:focus:border-neutral-100"
                        >{{ old('description', $proposed->description) }}</textarea>
                    </div>

                    @if ($submission->status->isOpen())
                        <p class="text-xs text-neutral-500 dark:text-neutral-400">
                            Anything you change here is locked, so a later scrape cannot undo it.
                            Fields you leave alone stay open to a better source.
                        </p>

                        <button type="submit" class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">
                            Approve
                        </button>
                    @endif
                </form>

                @if ($submission->status->isOpen())
                    <form method="POST" action="{{ route('submissions.reject', $submission) }}" class="border-t border-neutral-200 px-5 py-5 dark:border-neutral-800">
                        @csrf
                        <label for="review_note" class="block text-xs uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Reason for rejecting (optional)</label>
                        <div class="mt-1 flex flex-wrap gap-2">
                            <input
                                id="review_note"
                                type="text"
                                name="review_note"
                                placeholder="The photograph is not a book"
                                class="min-w-64 flex-1 rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm focus:border-neutral-900 focus:outline-none dark:border-neutral-700 dark:bg-neutral-900 dark:focus:border-neutral-100"
                            >
                            <button type="submit" class="rounded-md border border-rose-300 px-4 py-2 text-sm font-medium text-rose-700 hover:bg-rose-50 dark:border-rose-900 dark:text-rose-400 dark:hover:bg-rose-950">
                                Reject
                            </button>
                        </div>
                        <p class="mt-2 text-xs text-neutral-500 dark:text-neutral-400">
                            The photograph is deleted and the catalogue is left untouched.
                        </p>
                    </form>
                @endif
            </x-card>
        </div>
    </div>
</x-layouts.app>
