<div @if($isExecuting) wire:poll.3s="checkCommandStatus" @endif>
    <flux:heading size="xl">Clone Existing Website</flux:heading>
    <flux:subheading class="mt-1">Clone a production WordPress site to your local environment</flux:subheading>

    @if($showProgress)
        {{-- Clone progress: steps + terminal side-by-side on large screens --}}
        <div class="mt-8 grid grid-cols-1 lg:grid-cols-3 gap-6 lg:gap-8 lg:items-start [min-height:calc(100dvh-10rem)]">
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
                        <div class="min-w-0 flex-1">
                            <flux:text class="{{ $step['status'] === 'pending' ? 'text-zinc-400' : '' }}">
                                {{ $step['label'] }}
                            </flux:text>
                            @if(! empty($step['hint']))
                                <flux:text class="text-xs text-zinc-500 dark:text-zinc-400 mt-1 leading-snug">
                                    {{ $step['hint'] }}
                                </flux:text>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="min-w-0 flex flex-col min-h-0 lg:col-span-2 lg:sticky lg:top-6 lg:self-start">
                <flux:heading size="sm" class="mb-2 shrink-0">Output</flux:heading>
                <div
                    id="terminal-output"
                    x-data="{ autoScroll: true }"
                    wire:key="clone-progress-terminal"
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

            <div class="col-span-full lg:col-span-3 flex flex-wrap items-center gap-3">
                @if($executionFailed)
                    <flux:button wire:click="retryFromFailedStep" variant="primary" icon="arrow-path">
                        Retry Failed Step
                    </flux:button>
                    @if($this->lastError)
                        <flux:text class="text-xs text-red-600 dark:text-red-400 font-mono break-all">
                            {{ $this->lastError }}
                        </flux:text>
                    @endif
                    @if($this->failedLogFile)
                        <flux:text class="text-xs text-zinc-400 font-mono break-all">
                            Log: {{ $this->failedLogFile }}
                        </flux:text>
                    @endif
                @endif

                @if(!$isExecuting && !$executionFailed && $siteId)
                    <flux:button href="{{ route('sites.show', $siteId, false) }}" variant="primary" icon-trailing="arrow-right" wire:navigate>
                        Go to Site Dashboard
                    </flux:button>
                @endif
            </div>
        </div>
    @elseif($connectionCheckInProgress)
        {{-- Connection Check In Progress --}}
        <div class="mt-12 flex flex-col items-center justify-center space-y-4">
            <div class="w-12 h-12 rounded-full bg-blue-100 dark:bg-blue-800 flex items-center justify-center">
                <flux:icon name="arrow-path" class="w-6 h-6 text-blue-600 dark:text-blue-400 animate-spin" />
            </div>
            <flux:heading size="lg">Checking connection...</flux:heading>
            <flux:text class="text-zinc-500 text-center max-w-md">
                Verifying SSH access and database credentials before starting the clone.
                This helps catch credential issues early.
            </flux:text>
        </div>
    @elseif($connectionCheckFailed)
        {{-- Connection Check Failed - Show Fix Form --}}
        <div class="mt-8 space-y-6">
            <flux:callout variant="danger" icon="exclamation-triangle" heading="Connection check failed (attempt {{ $connectionAttemptCount }})" class="max-w-2xl">
                <flux:text>
                    @if($connectionFailurePhase === 'ssh')
                        SSH could not connect to the server. The saved password may be wrong, or the server may be unreachable.
                    @else
                        SSH connected, but MySQL rejected the database user or password. Use the credentials from production <code class="text-xs">wp-config.php</code> (often different from SSH credentials).
                    @endif
                </flux:text>
                @if($connectionFailureMessage !== '')
                    <flux:text class="mt-2 font-mono text-xs whitespace-pre-wrap break-all">{{ $connectionFailureMessage }}</flux:text>
                @endif
            </flux:callout>

            <div class="space-y-4 max-w-xl">
                <flux:field>
                    <flux:label>SSH Password</flux:label>
                    <flux:input type="password" wire:model="connectionFixSshPassword" autocomplete="new-password" placeholder="Leave blank to use saved password" />
                    <flux:description>Only enter if SSH password has changed or is wrong</flux:description>
                </flux:field>

                <flux:field>
                    <flux:label>Database Password</flux:label>
                    <flux:input type="password" wire:model="connectionFixDbPassword" autocomplete="new-password" placeholder="From production wp-config.php" />
                    <flux:description>The MySQL password (usually different from SSH)</flux:description>
                </flux:field>
            </div>

            <flux:callout variant="info" icon="information-circle" class="max-w-2xl">
                <flux:text>
                    When you click <strong>Retry Connection</strong>, the new passwords will be tested. If they work, they will be saved (encrypted) for future clones. You can retry as many times as needed, or click <strong>Cancel Clone</strong> to abort.
                </flux:text>
            </flux:callout>

            <div class="flex items-center gap-3 pt-4">
                <flux:button wire:click="retryConnection" variant="primary" icon="arrow-path">
                    Retry Connection
                </flux:button>
                <flux:button wire:click="cancelCloneAttempt" variant="danger" icon="x-mark">
                    Cancel Clone
                </flux:button>
            </div>
        </div>
    @else
        {{-- Initial Form --}}
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
            <form wire:submit="startClone" class="mt-8 w-full max-w-none space-y-6">
                <div class="grid grid-cols-2 gap-4">
                    <div class="min-w-0 space-y-3">
                        <label for="clone-remote-site-id" class="block text-sm font-medium text-zinc-800 dark:text-white">
                            Remote Site
                        </label>
                        <select
                            id="clone-remote-site-id"
                            name="remoteSiteId"
                            wire:model.blur.number="remoteSiteId"
                            @if ($errors->has('remoteSiteId'))
                                aria-invalid="true"
                            @endif
                            @class([
                                'appearance-none w-full ps-3 pe-10 block h-10 py-2 text-base sm:text-sm leading-[1.375rem] rounded-lg shadow-xs border bg-white dark:bg-white/10 text-zinc-700 dark:text-zinc-300 dark:[&>option]:bg-zinc-700 dark:[&>option]:text-white',
                                'border border-red-500' => $errors->has('remoteSiteId'),
                                'border border-zinc-200 border-b-zinc-300/80 dark:border-white/10' => ! $errors->has('remoteSiteId'),
                            ])
                        >
                            <option value="" @selected($remoteSiteId === null)>Select a remote site...</option>
                            @foreach ($this->remoteSites as $remote)
                                <option value="{{ $remote->id }}" @selected((int) $remoteSiteId === (int) $remote->id)>
                                    {{ $remote->remote_domain }} ({{ $remote->theme_name ?? 'No theme' }})
                                </option>
                            @endforeach
                        </select>
                        @error('remoteSiteId')
                            <p class="text-sm font-medium text-red-500 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <flux:field>
                        <flux:label>Local site name</flux:label>
                        <div class="flex h-10 items-stretch overflow-hidden rounded-lg shadow-xs border border-zinc-200 border-b-zinc-300/80 bg-white divide-x divide-zinc-200 dark:divide-white/10 dark:border-white/10 dark:border-b-white/5 dark:bg-white/10 focus-within:ring-2 focus-within:ring-zinc-400/30 focus-within:border-zinc-300 dark:focus-within:ring-white/20 dark:focus-within:border-white/20">
                            <span class="inline-flex items-center px-3 text-xs font-mono text-zinc-500 bg-zinc-50 select-none sm:text-sm dark:text-zinc-400 dark:bg-zinc-900/50" aria-hidden="true">https://</span>
                            <input type="text" wire:model.live.debounce.300ms="siteName" placeholder="my-site" autocomplete="off" class="min-w-0 flex-1 border-0 bg-transparent px-3 text-sm text-zinc-700 placeholder-zinc-400 focus:outline-none focus:ring-0 dark:text-zinc-200 dark:placeholder-zinc-500" />
                            <span class="inline-flex items-center px-3 text-xs font-mono text-zinc-500 bg-zinc-50 select-none sm:text-sm dark:text-zinc-400 dark:bg-zinc-900/50" aria-hidden="true">.lndo.site</span>
                        </div>
                        <flux:error name="siteName" />
                    </flux:field>
                </div>

                <flux:field>
                    <flux:label>Project Path</flux:label>
                    <flux:input wire:model="path" />
                    <flux:description>Where the site files will be created</flux:description>
                </flux:field>

                <flux:callout variant="info" icon="information-circle" class="w-full">
                    <flux:text>
                        This process can take a long time because it dumps the DB from the remote server, imports it, search replace the remote-url for the local-url, installs the theme from the repo, installs Composer dependencies, installs Node dependencies and build the project assets.
                    </flux:text>
                </flux:callout>

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
    @endif
</div>
