@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ $title ? $title.' — ' : '' }}{{ config('app.name') }}</title>

    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-neutral-50 font-sans text-neutral-900 antialiased dark:bg-neutral-950 dark:text-neutral-100">
    <header class="border-b border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center gap-x-8 gap-y-3 px-4 py-4 sm:px-6 lg:px-8">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-2 text-sm font-semibold tracking-tight">
                <span aria-hidden="true">📚</span>
                {{ config('app.name') }}
            </a>

            <nav class="flex items-center gap-1 text-sm">
                @foreach ([
                    ['route' => 'dashboard', 'pattern' => 'dashboard', 'label' => 'Dashboard'],
                    ['route' => 'books.index', 'pattern' => 'books.*', 'label' => 'Books'],
                    ['route' => 'scrape-runs.index', 'pattern' => 'scrape-runs.*', 'label' => 'Scrape runs'],
                ] as $item)
                    @php($active = request()->routeIs($item['pattern']))
                    <a
                        href="{{ route($item['route']) }}"
                        @class([
                            'rounded-md px-3 py-1.5 transition',
                            'bg-neutral-900 text-white dark:bg-neutral-100 dark:text-neutral-900' => $active,
                            'text-neutral-600 hover:bg-neutral-100 dark:text-neutral-400 dark:hover:bg-neutral-800' => ! $active,
                        ])
                        @if ($active) aria-current="page" @endif
                    >{{ $item['label'] }}</a>
                @endforeach
            </nav>
        </div>
    </header>

    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        @isset($heading)
            <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">{{ $heading }}</h1>
                    @isset($subheading)
                        <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">{{ $subheading }}</p>
                    @endisset
                </div>
                @isset($actions){{ $actions }}@endisset
            </div>
        @endisset

        {{ $slot }}
    </main>
</body>
</html>
