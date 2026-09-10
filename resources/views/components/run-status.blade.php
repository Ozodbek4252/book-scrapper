@props(['status'])

@php
    $styles = match ($status) {
        \App\Enums\ScrapeRunStatus::Completed => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
        \App\Enums\ScrapeRunStatus::Running => 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300',
        \App\Enums\ScrapeRunStatus::Failed => 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300',
        \App\Enums\ScrapeRunStatus::Pending => 'bg-neutral-100 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {$styles}"]) }}>
    {{ $status->name }}
</span>
