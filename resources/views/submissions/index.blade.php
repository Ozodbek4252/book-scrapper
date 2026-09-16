<x-layouts.app title="Submissions">
    <x-slot:heading>Submissions</x-slot:heading>
    <x-slot:subheading>Books sent in from the app, waiting on a human.</x-slot:subheading>

    <x-slot:actions>
        <nav class="flex items-center gap-1 text-sm">
            @foreach (\App\Enums\SubmissionStatus::cases() as $case)
                @php($active = $status === $case)
                <a
                    href="{{ route('submissions.index', ['status' => $case->value]) }}"
                    @class([
                        'rounded-md px-3 py-1.5 transition',
                        'bg-neutral-900 text-white dark:bg-neutral-100 dark:text-neutral-900' => $active,
                        'text-neutral-600 hover:bg-neutral-100 dark:text-neutral-400 dark:hover:bg-neutral-800' => ! $active,
                    ])
                >
                    {{ ucfirst($case->value) }}
                    <span class="tabular-nums opacity-60">{{ $counts[$case->value] ?? 0 }}</span>
                </a>
            @endforeach
        </nav>
    </x-slot:actions>

    <x-card>
        @if ($submissions->isEmpty())
            <x-empty-state :message="$status === \App\Enums\SubmissionStatus::Pending
                ? 'Nothing is waiting. Everything sent in has been decided.'
                : 'No '.$status->value.' submissions.'" />
        @else
            <ul class="divide-y divide-neutral-200 dark:divide-neutral-800">
                @foreach ($submissions as $submission)
                    @php($proposed = $submission->toRawBook())
                    <li>
                        <a href="{{ route('submissions.show', $submission) }}"
                           class="flex items-start gap-4 px-5 py-4 hover:bg-neutral-50 dark:hover:bg-neutral-800/50">
                            <div class="flex h-16 w-12 shrink-0 items-center justify-center overflow-hidden rounded bg-neutral-100 text-xs text-neutral-400 dark:bg-neutral-800">
                                @if ($submission->cover_path)
                                    📷
                                @else
                                    —
                                @endif
                            </div>

                            <div class="min-w-0 flex-1">
                                <p class="truncate font-medium">{{ $proposed->title ?: '(no title)' }}</p>
                                <p class="mt-0.5 truncate text-sm text-neutral-500 dark:text-neutral-400">
                                    {{ implode(', ', $proposed->authors) ?: 'no author given' }}
                                    @if ($proposed->isbn) · <span class="font-mono text-xs">{{ $proposed->isbn }}</span> @endif
                                </p>
                                <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                                    @if ($submission->isUpdate())
                                        <span class="rounded bg-amber-100 px-1.5 py-0.5 text-amber-900 dark:bg-amber-950 dark:text-amber-300">change</span>
                                        to “{{ Str::limit($submission->book?->title ?? 'a deleted book', 46) }}”
                                    @else
                                        <span class="rounded bg-blue-100 px-1.5 py-0.5 text-blue-900 dark:bg-blue-950 dark:text-blue-300">new book</span>
                                    @endif
                                    · {{ $submission->created_at->diffForHumans() }}
                                    @if ($submission->device) · {{ $submission->device->platform ?? 'device' }} @endif
                                </p>
                            </div>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>

    @if ($submissions->hasPages())
        <div class="mt-6">{{ $submissions->links() }}</div>
    @endif
</x-layouts.app>
