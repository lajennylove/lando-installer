<div @if($isExecuting) wire:poll.3s="checkCommandStatus" @endif>
    <flux:heading size="xl">Clone Existing Website</flux:heading>
    <flux:subheading class="mt-1">Clone a production WordPress site to your local environment</flux:subheading>

    @if(!$showProgress)
        @if($this->remoteSites->isEmpty())
            {{-- No remote sites configured --}}
            <div class="mt-8 p-8 rounded-xl border-2 border-dashed border-zinc-300 dark:border-zinc-600 text-center">
                <flux:icon name="server-stack" class="w-12 h-12 mx-auto text-zinc-400 mb-4" />
                <flux:heading size="lg">No Remote Sites Configured</flux:heading>
                <flux:text class="mt-2 text-zinc-500">
                    You need to add remote site configurations in Settings before you can clone.
                </flux:text>
                <div class="mt-4">
                    <flux:button href="{{ route('settings', [], false) }}" variant="primary" icon="cog-6-tooth" wire:navigate>
                        Go to Settings
                    </flux:button>
                </div>
            </div>
        @else
            {{-- Clone Form --}}
            <form wire:submit="startClone" class="mt-8 max-w-lg space-y-6">
                <flux:field>
                    <flux:label>Remote Site</flux:label>
                    <flux:select wire:model.live="remoteSiteId" placeholder="Select a remote site...">
                        @foreach($this->remoteSites as $remote)
                            <flux:select.option value="{{ $remote->id }}">
                                {{ $remote->remote_domain }} ({{ $remote->theme_name ?? 'No theme' }})
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="remoteSiteId" />
                </flux:field>

                <flux:field>
                    <flux:label>Local Site Name</flux:label>
                    <flux:input wire:model.live.debounce.300ms="siteName" placeholder="site-name" />
                    <flux:error name="siteName" />
                </flux:field>

                <flux:field>
                    <flux:label>Project Path</flux:label>
                    <flux:input wire:model="path" />
                    <flux:description>Where the site files will be created</flux:description>
                </flux:field>

                <div class="flex items-center gap-3 pt-4">
                    <flux:button type="submit" variant="primary" icon="arrow-down-tray">
                        Start Clone
                    </flux:button>
                    <flux:button href="{{ route('create', [], false) }}" variant="ghost" wire:navigate>
                        Cancel
                    </flux:button>
                </div>
            </form>
        @endif
    @else
        {{-- Progress View --}}
        <div class="mt-8 space-y-6">
            <div class="space-y-2">
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

            @if($terminalOutput)
                <div>
                    <flux:heading size="sm" class="mb-2">Output</flux:heading>
                    <div
                        id="terminal-output"
                        x-data="{ autoScroll: true }"
                        wire:key="terminal-{{ md5($terminalOutput) }}"
                        x-effect="if (autoScroll) $nextTick(() => $el.scrollTop = $el.scrollHeight)"
                        @scroll="autoScroll = ($el.scrollTop + $el.clientHeight >= $el.scrollHeight - 50)"
                        class="bg-zinc-900 text-green-400 font-mono text-xs p-4 rounded-lg h-72 overflow-y-auto"
                    >
                        @foreach(explode("\n", $terminalOutput) as $line)
                            <span class="terminal-line">{!! $line === '' ? '&nbsp;' : \App\Support\AnsiToHtml::lineToHtml($line) !!}</span>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="flex items-center gap-3">
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
