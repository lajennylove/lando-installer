<div @if($installing) wire:poll.3s="pollInstallStatus" @endif>
    <div class="grid lg:grid-cols-2 gap-6 h-full">

        {{-- Left column: dependency list --}}
        <div class="flex flex-col">
            <div class="text-center mb-6">
                <img src="{{ asset('assets/rocket.png') }}" alt="LandoDEV" class="mx-auto mb-4 size-[60px] object-contain" />
                <flux:heading size="xl">Welcome to LandoDEV</flux:heading>
                <flux:subheading class="mt-2">Let's make sure you have everything you need</flux:subheading>
            </div>

            <div class="space-y-3">
                @foreach($dependencies as $key => $dep)
                    <div class="flex items-center justify-between p-4 rounded-lg border {{ $dep['installed'] ? 'border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-900/20' : ($dep['required'] ? 'border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/20' : 'border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800') }}">
                        <div class="flex items-center gap-3">
                            @if($dep['installed'])
                                <div class="w-8 h-8 shrink-0 rounded-full bg-green-100 dark:bg-green-800 flex items-center justify-center">
                                    <flux:icon name="check" class="w-5 h-5 text-green-600 dark:text-green-400" />
                                </div>
                            @else
                                <div class="w-8 h-8 shrink-0 rounded-full bg-red-100 dark:bg-red-800 flex items-center justify-center">
                                    <flux:icon name="x-mark" class="w-5 h-5 text-red-600 dark:text-red-400" />
                                </div>
                            @endif
                            <div>
                                <div class="flex items-center gap-2">
                                    <flux:heading size="sm">{{ $dep['label'] }}</flux:heading>
                                    @if(!$dep['required'])
                                        <flux:badge size="sm" color="zinc">Optional</flux:badge>
                                    @endif
                                </div>
                                <flux:text class="text-sm text-zinc-500">
                                    {{ $dep['description'] }}
                                    @if($dep['version'])
                                        <span class="text-zinc-400">- {{ $dep['version'] }}</span>
                                    @endif
                                </flux:text>
                            </div>
                        </div>

                        @if(!$dep['installed'])
                            <flux:button
                                wire:click="install('{{ $key }}')"
                                size="sm"
                                variant="primary"
                                :disabled="$installing"
                            >
                                @if($installing && $installingDep === $key)
                                    <flux:icon name="arrow-path" class="w-4 h-4 animate-spin" />
                                    Installing...
                                @else
                                    Install
                                @endif
                            </flux:button>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="mt-auto pt-6 flex justify-end">
                @if($this->allRequiredMet())
                    <flux:button href="{{ route('home') }}" variant="primary" icon-trailing="arrow-right">
                        Continue to LandoDEV
                    </flux:button>
                @else
                    <flux:button disabled variant="primary" icon-trailing="arrow-right">
                        Install required dependencies to continue
                    </flux:button>
                @endif
            </div>
        </div>

        {{-- Right column: terminal --}}
        <div class="flex flex-col">
            <div class="flex items-center justify-between mb-2">
                <flux:heading size="sm" class="text-zinc-500">Install Log</flux:heading>
                @if($installing)
                    <div class="flex items-center gap-2 text-blue-600 dark:text-blue-400">
                        <flux:icon name="arrow-path" class="w-4 h-4 animate-spin" />
                        <flux:text class="text-xs">Installing {{ $dependencies[$installingDep]['label'] ?? '' }}…</flux:text>
                    </div>
                @endif
            </div>
            <div
                id="terminal-output"
                x-data="{ autoScroll: true }"
                wire:key="setup-terminal"
                @landodev-scroll-terminal.window="if (autoScroll) { requestAnimationFrame(() => { $el.scrollTop = $el.scrollHeight }) }"
                @scroll="autoScroll = ($el.scrollTop + $el.clientHeight >= $el.scrollHeight - 50)"
                class="bg-zinc-900 text-green-400 font-mono text-xs p-4 rounded-lg overflow-y-auto flex-1 min-h-[20rem] h-[min(32rem,calc(100dvh-14rem))] lg:min-h-[calc(100dvh-10rem)] lg:h-[calc(100dvh-10rem)]"
            >
                @if($installOutput !== '')
                    @foreach(explode("\n", $installOutput) as $line)
                        <span class="block terminal-line">{!! $line === '' ? '&nbsp;' : \App\Support\AnsiToHtml::lineToHtml($line) !!}</span>
                    @endforeach
                @else
                    <span class="text-zinc-600">No output yet. Click Install to begin.</span>
                @endif
            </div>
        </div>

    </div>
</div>
