@props(['title' => null])

<section {{ $attributes->merge(['class' => 'overflow-hidden rounded-xl border border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900']) }}>
    @if ($title)
        <h2 class="border-b border-neutral-200 px-5 py-3 text-sm font-semibold dark:border-neutral-800">{{ $title }}</h2>
    @endif
    {{ $slot }}
</section>
