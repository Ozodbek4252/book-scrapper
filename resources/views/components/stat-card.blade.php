@props(['label', 'value', 'hint' => null])

<div {{ $attributes->merge(['class' => 'rounded-xl border border-neutral-200 bg-white p-5 dark:border-neutral-800 dark:bg-neutral-900']) }}>
    <dt class="text-sm text-neutral-500 dark:text-neutral-400">{{ $label }}</dt>
    <dd class="mt-2 text-3xl font-semibold tabular-nums tracking-tight">{{ $value }}</dd>
    @if ($hint)
        <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">{{ $hint }}</p>
    @endif
</div>
