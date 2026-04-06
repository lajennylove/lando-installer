@props([
    'title' => null,
])
@php
    $documentTitle = filled($title) ? $title : config('app.name', 'LandoDEV');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ $documentTitle }}</title>

    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="{{ asset('assets/rocket.png') }}" type="image/png">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Styles / Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @fluxAppearance

    @php $savedAppearance = \App\Models\UserPreference::get('appearance', 'system'); @endphp
    <script>
        (function () {
            var saved = @json($savedAppearance);
            if (saved === 'dark') {
                document.documentElement.classList.add('dark');
            } else if (saved === 'light') {
                document.documentElement.classList.remove('dark');
            }
        })();
    </script>
</head>

<body class="min-h-screen bg-white dark:bg-zinc-800">

<flux:sidebar sticky stashable class="bg-zinc-50 dark:bg-zinc-900 border-r rtl:border-r-0 rtl:border-l border-zinc-200 dark:border-zinc-700">
    <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

    <flux:brand href="/" logo="{{ asset('assets/rocket.png') }}" name="LandoDEV" class="px-2 dark:hidden" />
    <flux:brand href="/" logo="{{ asset('assets/rocket.png') }}" name="LandoDEV" class="px-2 hidden dark:flex" />

    <div class="px-3 mt-2">
        <flux:button href="{{ route('create', [], false) }}" variant="primary" class="w-full justify-center" icon="plus" wire:navigate>
            Create Site
        </flux:button>
    </div>

    <flux:separator variant="subtle" class="my-2" />

    <div class="flex-1 overflow-y-auto px-1">
        @livewire('site-list')
    </div>

    <flux:spacer />

    <flux:navlist variant="outline">
        @foreach($secondMenu as $item)
            @if($item['title'] === 'separator')
                <flux:separator variant="subtle" />
                @continue
            @endif
            <flux:navlist.item :href="route($item['route'], [], false)" :icon="$item['icon']" :current="request()->routeIs($item['route'].'*')" wire:navigate>
                {{ __($item['title']) }}
            </flux:navlist.item>
        @endforeach
    </flux:navlist>
</flux:sidebar>

<flux:header class="lg:hidden">
    <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />
    <flux:spacer />
</flux:header>

<flux:main>
    {{ $main ?? $slot }}
</flux:main>

<x-notifications />

@fluxScripts

<script>
    document.addEventListener('livewire:navigated', function () {
        var t = document.title && document.title.trim();
        if (!t) {
            document.title = @json(config('app.name', 'LandoDEV'));
        }
    });
</script>

@if(config('nativephp-internal.running'))
    {{-- Electron opens target=_blank in a new app window; send real URLs to the system browser instead. --}}
    <script>
        document.addEventListener('click', function (e) {
            if (e.defaultPrevented) {
                return;
            }
            const a = e.target.closest && e.target.closest('a[href]');
            if (!a || a.hasAttribute('wire:navigate')) {
                return;
            }
            const href = a.getAttribute('href');
            if (!href || href.startsWith('#') || href.toLowerCase().startsWith('javascript:')) {
                return;
            }
            let url;
            try {
                url = new URL(href, window.location.href);
            } catch (_) {
                return;
            }
            if (url.protocol !== 'http:' && url.protocol !== 'https:') {
                return;
            }
            if (url.origin === window.location.origin) {
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            try {
                if (typeof require === 'function') {
                    require('electron').shell.openExternal(url.href);
                } else {
                    window.open(url.href, '_blank', 'noopener,noreferrer');
                }
            } catch (_) {
                window.open(url.href, '_blank', 'noopener,noreferrer');
            }
        }, true);
    </script>
@endif

</body>
</html>
