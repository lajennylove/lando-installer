<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>LandoDEV</title>

    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/assets/rocket.svg" type="image/svg+xml">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Styles / Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @fluxAppearance
</head>

<body class="min-h-screen bg-white dark:bg-zinc-800">

<flux:sidebar sticky stashable class="bg-zinc-50 dark:bg-zinc-900 border-r rtl:border-r-0 rtl:border-l border-zinc-200 dark:border-zinc-700">
    <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

    <flux:brand href="/" logo="/assets/rocket.svg" name="LandoDEV" class="px-2 dark:hidden" />
    <flux:brand href="/" logo="/assets/rocket.svg" name="LandoDEV" class="px-2 hidden dark:flex" />

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

</body>
</html>
