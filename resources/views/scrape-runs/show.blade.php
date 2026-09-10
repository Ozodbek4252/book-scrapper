<x-layouts.app :title="'Run '.$run->id">
    <x-slot:heading>{{ $run->source_key }}</x-slot:heading>
    <x-slot:subheading>Run #{{ $run->id }}</x-slot:subheading>

    <x-slot:actions>
        <a href="{{ route('scrape-runs.index') }}" class="text-sm text-neutral-500 hover:underline dark:text-neutral-400">← Back to runs</a>
    </x-slot:actions>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <x-stat-card label="Pages" :value="Number::format($run->pages_scraped)" />
        <x-stat-card label="Found" :value="Number::format($run->items_found)" />
        <x-stat-card label="New" :value="Number::format($run->items_new)" />
        <x-stat-card label="Updated" :value="Number::format($run->items_updated)" />
        <x-stat-card label="Errors" :value="Number::format($run->errors_count)" />
    </div>

    <x-card title="Timing" class="mt-6">
        <dl class="grid gap-x-6 gap-y-4 px-5 py-5 sm:grid-cols-3">
            <div>
                <dt class="text-xs uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Status</dt>
                <dd class="mt-1"><x-run-status :status="$run->status" /></dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Started</dt>
                <dd class="mt-1 text-sm">{{ $run->started_at?->toDateTimeString() ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Finished</dt>
                <dd class="mt-1 text-sm">{{ $run->finished_at?->toDateTimeString() ?? '—' }}</dd>
            </div>
        </dl>
    </x-card>

    <x-card title="Errors ({{ Number::format($errors->total()) }})" class="mt-6">
        @if ($errors->isEmpty())
            <x-empty-state message="This run logged no errors." />
        @else
            <ul class="divide-y divide-neutral-200 dark:divide-neutral-800">
                @foreach ($errors as $error)
                    <li class="px-5 py-4">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded bg-neutral-100 px-2 py-0.5 font-mono text-xs dark:bg-neutral-800">{{ $error->stage->value }}</span>
                            <span class="text-xs text-neutral-500 dark:text-neutral-400">{{ $error->created_at->toDateTimeString() }}</span>
                        </div>
                        <p class="mt-1 text-sm">{{ $error->message }}</p>
                        @if ($error->url)
                            <p class="mt-1 truncate text-xs text-neutral-500 dark:text-neutral-400">{{ $error->url }}</p>
                        @endif
                        @if ($error->context)
                            <details class="mt-2">
                                <summary class="cursor-pointer text-xs text-neutral-500 hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-neutral-100">Context</summary>
                                <pre class="mt-2 max-h-48 overflow-auto rounded-lg bg-neutral-100 p-3 text-xs dark:bg-neutral-950"><code>{{ json_encode($error->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</code></pre>
                            </details>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>

    @if ($errors->hasPages())
        <div class="mt-6">{{ $errors->links() }}</div>
    @endif
</x-layouts.app>
