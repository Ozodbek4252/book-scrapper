<x-layouts.app title="Dashboard">
    <x-slot:heading>Dashboard</x-slot:heading>
    <x-slot:subheading>Books published in Uzbekistan, merged from every source.</x-slot:subheading>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat-card label="Books" :value="Number::format($summary['books'])" />
        <x-stat-card
            label="With ISBN"
            :value="$summary['with_isbn_percent'].'%'"
            :hint="Number::format($summary['with_isbn']).' of '.Number::format($summary['books'])"
        />
        <x-stat-card
            label="With cover"
            :value="$summary['with_cover_percent'].'%'"
            :hint="Number::format($summary['with_cover']).' of '.Number::format($summary['books'])"
        />
        <x-stat-card
            label="Added in {{ $summary['recent_days'] }} days"
            :value="Number::format($summary['added_recently'])"
        />
    </div>

    <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat-card label="Authors" :value="Number::format($summary['authors'])" />
        <x-stat-card label="Publishers" :value="Number::format($summary['publishers'])" />
        <x-stat-card label="Verified" :value="Number::format($summary['verified'])" />
        <x-stat-card
            label="Awaiting review"
            :value="Number::format($summary['unverified'])"
            hint="Unverified or user submitted"
        />
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        <x-card title="Sources">
            @if ($sources->isEmpty())
                <x-empty-state message="No source is configured in config/scraping.php." />
            @else
                <ul class="divide-y divide-neutral-200 dark:divide-neutral-800">
                    @foreach ($sources as $source)
                        <li class="flex flex-wrap items-center justify-between gap-2 px-5 py-3 text-sm">
                            <span class="flex items-center gap-2">
                                <span class="font-mono">{{ $source['key'] }}</span>
                                @if (! $source['has_driver'])
                                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-900 dark:bg-amber-950 dark:text-amber-300">
                                        No driver yet
                                    </span>
                                @elseif ($source['enabled'])
                                    <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                        Enabled
                                    </span>
                                @else
                                    <span class="rounded-full bg-neutral-100 px-2 py-0.5 text-xs text-neutral-600 dark:bg-neutral-800 dark:text-neutral-400">
                                        Disabled
                                    </span>
                                @endif
                            </span>
                            <span class="tabular-nums text-neutral-500 dark:text-neutral-400">
                                {{ Number::format($source['books']) }} books
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        <x-card title="Recent scrape runs">
            @if ($recentRuns->isEmpty())
                <x-empty-state message="No scrape has run yet." />
            @else
                <ul class="divide-y divide-neutral-200 dark:divide-neutral-800">
                    @foreach ($recentRuns as $run)
                        <li>
                            <a
                                href="{{ route('scrape-runs.show', $run) }}"
                                class="flex items-center justify-between gap-4 px-5 py-3 text-sm hover:bg-neutral-50 dark:hover:bg-neutral-800"
                            >
                                <span class="flex items-center gap-3">
                                    <x-run-status :status="$run->status" />
                                    <span class="font-mono">{{ $run->source_key }}</span>
                                </span>
                                <span class="tabular-nums text-neutral-500 dark:text-neutral-400">
                                    {{ Number::format($run->items_found) }} found
                                    @if ($run->errors_count > 0)
                                        · {{ Number::format($run->errors_count) }} errors
                                    @endif
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>
</x-layouts.app>
