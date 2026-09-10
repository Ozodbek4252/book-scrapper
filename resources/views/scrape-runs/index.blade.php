<x-layouts.app title="Scrape runs">
    <x-slot:heading>Scrape runs</x-slot:heading>
    <x-slot:subheading>Every catalogue walk, newest first.</x-slot:subheading>

    <x-slot:actions>
        <form method="POST" action="{{ route('scrape-runs.store') }}" class="flex flex-wrap items-center gap-2">
            @csrf
            <label for="source_key" class="sr-only">Source</label>
            <select
                id="source_key"
                name="source_key"
                class="rounded-md border border-neutral-300 bg-white px-3 py-1.5 text-sm focus:border-neutral-900 focus:outline-none dark:border-neutral-700 dark:bg-neutral-900 dark:focus:border-neutral-100"
            >
                @foreach ($sourceKeys as $key)
                    <option value="{{ $key }}">{{ $key }}</option>
                @endforeach
            </select>
            <button type="submit" class="rounded-md bg-neutral-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-neutral-700 dark:bg-neutral-100 dark:text-neutral-900 dark:hover:bg-neutral-300">
                Run now
            </button>
        </form>
    </x-slot:actions>

    <x-card>
        @if ($runs->isEmpty())
            <x-empty-state message="No scrape has run yet. Pick a source above and press Run now." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-neutral-200 text-xs uppercase tracking-wide text-neutral-500 dark:border-neutral-800 dark:text-neutral-400">
                        <tr>
                            <th scope="col" class="px-5 py-3 font-medium">Source</th>
                            <th scope="col" class="px-5 py-3 font-medium">Status</th>
                            <th scope="col" class="px-5 py-3 font-medium">Started</th>
                            <th scope="col" class="px-5 py-3 text-right font-medium">Pages</th>
                            <th scope="col" class="px-5 py-3 text-right font-medium">Found</th>
                            <th scope="col" class="px-5 py-3 text-right font-medium">New</th>
                            <th scope="col" class="px-5 py-3 text-right font-medium">Updated</th>
                            <th scope="col" class="px-5 py-3 text-right font-medium">Errors</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-200 dark:divide-neutral-800">
                        @foreach ($runs as $run)
                            <tr class="hover:bg-neutral-50 dark:hover:bg-neutral-800/50">
                                <td class="px-5 py-3">
                                    <a href="{{ route('scrape-runs.show', $run) }}" class="font-mono font-medium hover:underline">
                                        {{ $run->source_key }}
                                    </a>
                                </td>
                                <td class="px-5 py-3"><x-run-status :status="$run->status" /></td>
                                <td class="px-5 py-3 text-neutral-600 dark:text-neutral-400">
                                    {{ $run->started_at?->diffForHumans() ?? '—' }}
                                </td>
                                <td class="px-5 py-3 text-right tabular-nums">{{ Number::format($run->pages_scraped) }}</td>
                                <td class="px-5 py-3 text-right tabular-nums">{{ Number::format($run->items_found) }}</td>
                                <td class="px-5 py-3 text-right tabular-nums">{{ Number::format($run->items_new) }}</td>
                                <td class="px-5 py-3 text-right tabular-nums">{{ Number::format($run->items_updated) }}</td>
                                <td @class([
                                    'px-5 py-3 text-right tabular-nums',
                                    'text-rose-600 dark:text-rose-400' => $run->errors_count > 0,
                                ])>{{ Number::format($run->errors_count) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

    @if ($runs->hasPages())
        <div class="mt-6">{{ $runs->links() }}</div>
    @endif
</x-layouts.app>
