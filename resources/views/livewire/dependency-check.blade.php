<div @if($installing) wire:poll.3s="pollInstallStatus" @endif>
    <div class="max-w-2xl mx-auto">
        <div class="text-center mb-8">
            <div class="app-logo-frame mx-auto mb-4">
                <img src="/assets/rocket.svg" alt="LandoDEV" class="app-logo-img" />
            </div>
            <flux:heading size="xl">Welcome to LandoDEV</flux:heading>
            <flux:subheading class="mt-2">Let's make sure you have everything you need</flux:subheading>
        </div>

        <div class="space-y-4">
            @foreach($dependencies as $key => $dep)
                <div class="flex items-center justify-between p-4 rounded-lg border {{ $dep['installed'] ? 'border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-900/20' : ($dep['required'] ? 'border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/20' : 'border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800') }}">
                    <div class="flex items-center gap-3">
                        @if($dep['installed'])
                            <div class="w-8 h-8 rounded-full bg-green-100 dark:bg-green-800 flex items-center justify-center">
                                <flux:icon name="check" class="w-5 h-5 text-green-600 dark:text-green-400" />
                            </div>
                        @else
                            <div class="w-8 h-8 rounded-full bg-red-100 dark:bg-red-800 flex items-center justify-center">
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

        @if($installing)
            <div class="mt-4 p-3 rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800">
                <div class="flex items-center gap-2">
                    <flux:icon name="arrow-path" class="w-4 h-4 animate-spin text-blue-600" />
                    <flux:text class="text-sm text-blue-700 dark:text-blue-300">
                        Installing {{ $dependencies[$installingDep]['label'] ?? '' }}... This may take a few minutes.
                    </flux:text>
                </div>
            </div>
        @endif

        <div class="mt-8 flex justify-end">
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
</div>
