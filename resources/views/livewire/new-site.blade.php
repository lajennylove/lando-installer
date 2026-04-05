<div @if($isExecuting) wire:poll.3s="checkCommandStatus" @endif>
    <flux:heading size="xl">New Website</flux:heading>
    <flux:subheading class="mt-1">Create a new WordPress site; optionally install the Sage starter theme</flux:subheading>

    @if(!$showProgress)
        {{-- Creation Form --}}
        <form wire:submit="createSite" class="mt-8 w-full max-w-none space-y-6">
            <flux:field>
                <flux:label>Site Name</flux:label>
                <flux:input wire:model.live.debounce.300ms="siteName" placeholder="my-awesome-site" />
                <flux:error name="siteName" />
                <flux:description>Used as the Lando project name{{ $installSage ? ' and Sage theme folder name' : '' }}</flux:description>
            </flux:field>

            <flux:field>
                <flux:label>Project Path</flux:label>
                <flux:input wire:model="path" />
                <flux:description>Where the site files will be created</flux:description>
            </flux:field>

            <flux:separator />

            <flux:heading size="sm">WordPress Admin</flux:heading>

            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>Admin Username</flux:label>
                    <flux:input wire:model="adminUsername" />
                    <flux:error name="adminUsername" />
                </flux:field>

                <flux:field>
                    <flux:label>Admin Password</flux:label>
                    <flux:input wire:model="adminPassword" type="password" />
                    <flux:error name="adminPassword" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>Admin Email</flux:label>
                <flux:input wire:model="adminEmail" type="email" placeholder="admin@example.com" />
                <flux:error name="adminEmail" />
            </flux:field>

            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50/80 dark:bg-zinc-900/50 p-5 sm:p-6">
                <div class="flex flex-row items-center gap-5 sm:gap-6 min-w-0">
                    <div class="flex w-12 h-12 sm:w-28 sm:h-28 items-center justify-center" aria-hidden="true">
                        <svg class="h-full w-full object-contain" viewBox="0 0 81 74" preserveAspectRatio="xMidYMid meet" xmlns="http://www.w3.org/2000/svg">
                            <path d="M24.837 10.873l5.755 17.07-15.065-10.55L0 28.266l5.897 17.492.034.1h18.622L9.487 56.408l5.897 17.493.034.099h19.191l5.755-17.07 5.72 16.97.034.1H65.31l5.93-17.592-15.065-10.55h18.622l5.93-17.592-15.526-10.873-15.066 10.55 5.755-17.07L40.364 0 24.837 10.873zm.352 34.786l5.797-17.194h18.755l5.797 17.194-15.174 10.626-15.175-10.626z" fill="#525DDC" fill-rule="nonzero" />
                        </svg>
                    </div>
                    <div class="min-w-0 flex-1 pt-0.5">
                        <flux:checkbox wire:model.live="installSage" label="Install Sage theme" description="Installs Composer dependencies, runs Yarn, builds the assets, and activates the theme after everything is installed." />
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-3 pt-4">
                <flux:button type="submit" variant="primary" icon="rocket-launch">
                    Create Site
                </flux:button>
                <flux:button href="{{ route('create', [], false) }}" variant="ghost" wire:navigate>
                    Cancel
                </flux:button>
            </div>
        </form>
    @else
        {{-- Progress View --}}
        <div class="mt-8 grid grid-cols-1 lg:grid-cols-3 gap-6 lg:gap-8 lg:items-start [min-height:calc(100dvh-10rem)]">
            {{-- Step Progress --}}
            <div class="space-y-2 min-w-0 lg:col-span-1 lg:max-h-[calc(100dvh-10rem)] lg:overflow-y-auto lg:pr-1">
                @foreach($steps as $index => $step)
                    <div class="flex items-center gap-3 p-3 rounded-lg {{ match($step['status']) {
                        'completed' => 'bg-green-50 dark:bg-green-900/20',
                        'running' => 'bg-blue-50 dark:bg-blue-900/20',
                        'failed' => 'bg-red-50 dark:bg-red-900/20',
                        default => 'bg-zinc-50 dark:bg-zinc-800',
                    } }}">
                        <div class="flex-shrink-0">
                            @if($step['status'] === 'completed')
                                <div class="w-6 h-6 rounded-full bg-green-100 dark:bg-green-800 flex items-center justify-center">
                                    <flux:icon name="check" class="w-4 h-4 text-green-600 dark:text-green-400" />
                                </div>
                            @elseif($step['status'] === 'running')
                                <div class="w-6 h-6 rounded-full bg-blue-100 dark:bg-blue-800 flex items-center justify-center">
                                    <flux:icon name="arrow-path" class="w-4 h-4 text-blue-600 dark:text-blue-400 animate-spin" />
                                </div>
                            @elseif($step['status'] === 'failed')
                                <div class="w-6 h-6 rounded-full bg-red-100 dark:bg-red-800 flex items-center justify-center">
                                    <flux:icon name="x-mark" class="w-4 h-4 text-red-600 dark:text-red-400" />
                                </div>
                            @else
                                <div class="w-6 h-6 rounded-full bg-zinc-200 dark:bg-zinc-700 flex items-center justify-center">
                                    <span class="text-xs text-zinc-500">{{ $index + 1 }}</span>
                                </div>
                            @endif
                        </div>
                        <flux:text class="{{ $step['status'] === 'pending' ? 'text-zinc-400' : '' }}">
                            {{ $step['label'] }}
                        </flux:text>
                    </div>
                @endforeach
            </div>

            {{-- Terminal Output --}}
            <div class="min-w-0 flex flex-col min-h-0 lg:col-span-2 lg:sticky lg:top-6 lg:self-start">
                <flux:heading size="sm" class="mb-2 shrink-0">Output</flux:heading>
                <div
                    id="terminal-output"
                    x-data="{ autoScroll: true }"
                    wire:key="new-site-terminal"
                    @landodev-scroll-terminal.window="if (autoScroll) { requestAnimationFrame(() => { $el.scrollTop = $el.scrollHeight }) }"
                    @scroll="autoScroll = ($el.scrollTop + $el.clientHeight >= $el.scrollHeight - 50)"
                    class="bg-zinc-900 text-green-400 font-mono text-xs p-4 rounded-lg overflow-y-auto min-h-[12rem] h-[min(24rem,calc(100dvh-16rem))] lg:min-h-[calc(100dvh-12rem)] lg:h-[calc(100dvh-12rem)] lg:max-h-[calc(100dvh-12rem)]"
                >
                    @if($terminalOutput !== '')
                        @foreach(explode("\n", $terminalOutput) as $line)
                            <span class="terminal-line">{!! $line === '' ? '&nbsp;' : \App\Support\AnsiToHtml::lineToHtml($line) !!}</span>
                        @endforeach
                    @else
                        <span class="text-zinc-500 not-italic">Output from each step will stream here.</span>
                    @endif
                </div>
            </div>

            {{-- Actions --}}
            <div class="col-span-full lg:col-span-3 flex flex-wrap items-center gap-3">
                @if($executionFailed)
                    <flux:button wire:click="retryFromFailedStep" variant="primary" icon="arrow-path">
                        Retry Failed Step
                    </flux:button>
                @endif

                @if(!$isExecuting && !$executionFailed && $siteId)
                    <flux:button href="{{ route('sites.show', $siteId, false) }}" variant="primary" icon-trailing="arrow-right" wire:navigate>
                        Go to Site Dashboard
                    </flux:button>
                @endif
            </div>
        </div>
    @endif
</div>
