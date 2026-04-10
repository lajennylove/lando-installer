<div @if($actionRunning) wire:poll.3s="pollActionStatus" @endif>
    <div class="flex items-start justify-between">
        <div>
            <flux:heading size="xl">{{ $site->name }}</flux:heading>
            <div class="flex items-center gap-2 mt-1">
                <span class="inline-block w-2.5 h-2.5 rounded-full {{ match($site->status->value ?? $site->status) {
                    'running' => 'bg-green-500',
                    'creating' => 'bg-blue-500 animate-pulse',
                    'error' => 'bg-red-500',
                    'stopped' => 'bg-red-400',
                    default => 'bg-zinc-400',
                } }}"></span>
                <flux:text class="text-sm capitalize">{{ $site->status->value ?? $site->status }}</flux:text>
                <flux:button
                    wire:click="checkRealStatus"
                    wire:loading.attr="disabled"
                    wire:target="checkRealStatus"
                    size="xs"
                    variant="ghost"
                    icon="arrow-path"
                    title="Refresh real container status"
                    class="ml-1"
                />
            </div>
        </div>
    </div>

    @if($this->isProjectMissingOnDisk())
        <div class="mt-6 rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-900/50 dark:bg-amber-950/30">
            <flux:heading size="sm" class="text-amber-900 dark:text-amber-100">Project folder missing</flux:heading>
            <flux:text class="mt-1 text-sm text-amber-800 dark:text-amber-200/90">
                Nothing exists at <span class="font-mono">{{ $site->path }}</span>. Lando actions are disabled. You can remove this stale entry from the sidebar (your database only — nothing left on disk to delete).
            </flux:text>
            <div class="mt-3">
                <flux:button
                    wire:click="removeOrphanFromApp"
                    wire:confirm="Remove '{{ $site->name }}' from the app? This only deletes the app record."
                    size="sm"
                    variant="primary"
                >
                    Remove from app
                </flux:button>
            </div>
        </div>
    @endif

    {{-- Action Buttons --}}
    <div class="mt-6 flex flex-wrap items-center gap-3">
        <flux:button wire:click="startSite" variant="primary" size="sm" icon="play" :disabled="$actionRunning || $this->isProjectMissingOnDisk()">
            Start
        </flux:button>
        <flux:button wire:click="stopSite" size="sm" icon="stop" :disabled="$actionRunning || $this->isProjectMissingOnDisk()">
            Stop
        </flux:button>
        <flux:button wire:click="restartSite" size="sm" icon="arrow-path" :disabled="$actionRunning || $this->isProjectMissingOnDisk()">
            Restart
        </flux:button>
        <flux:button wire:click="rebuildSite" size="sm" icon="wrench" :disabled="$actionRunning || $this->isProjectMissingOnDisk()">
            Rebuild
        </flux:button>
        <flux:button wire:click="openInFinder" size="sm" icon="folder-open" :disabled="$actionRunning || $this->isProjectMissingOnDisk()">
            Open Folder
        </flux:button>
        <flux:button wire:click="confirmDestroy" variant="danger" size="sm" icon="trash" :disabled="$actionRunning">
            Destroy
        </flux:button>
    </div>

    @if($actionRunning)
        <div class="mt-4 p-3 rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800">
            <div class="flex items-center gap-2">
                <flux:icon name="arrow-path" class="w-4 h-4 animate-spin text-blue-600" />
                <flux:text class="text-sm text-blue-700 dark:text-blue-300">{{ $actionLabel }} site...</flux:text>
            </div>
        </div>
    @endif

    {{-- Site Details --}}
    <div class="mt-8 grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="space-y-4">
            <flux:heading size="sm">Site Details</flux:heading>

            <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 divide-y divide-zinc-200 dark:divide-zinc-700">
                <div class="flex justify-between items-center px-4 py-3">
                    <flux:text class="text-sm text-zinc-500">URL</flux:text>
                    @if($site->url)
                        <a href="{{ $site->url }}" target="_blank" class="text-sm text-blue-600 hover:underline">{{ $site->url }}</a>
                    @else
                        <flux:text class="text-sm text-zinc-400">--</flux:text>
                    @endif
                </div>
                <div class="flex justify-between items-center px-4 py-3">
                    <flux:text class="text-sm text-zinc-500">WP Admin</flux:text>
                    @if($site->admin_url)
                        <a href="{{ $site->admin_url }}" target="_blank" class="text-sm text-blue-600 hover:underline">{{ $site->admin_url }}</a>
                    @else
                        <flux:text class="text-sm text-zinc-400">--</flux:text>
                    @endif
                </div>
                <div class="flex justify-between items-center px-4 py-3">
                    <flux:text class="text-sm text-zinc-500">Admin User</flux:text>
                    <flux:text class="text-sm">{{ $site->admin_username ?? '--' }}</flux:text>
                </div>
                <div class="flex justify-between items-center px-4 py-3">
                    <flux:text class="text-sm text-zinc-500">Admin Password</flux:text>
                    <div class="flex items-center gap-2">
                        <flux:text class="text-sm text-zinc-400">********</flux:text>
                        <flux:button wire:click="showChangePassword" size="xs" variant="ghost" icon="pencil" :disabled="$this->isProjectMissingOnDisk()" />
                    </div>
                </div>
                <div class="flex justify-between items-center px-4 py-3">
                    <flux:text class="text-sm text-zinc-500">Project Path</flux:text>
                    <div class="flex items-center gap-2">
                        <flux:text class="text-sm font-mono truncate max-w-xs">{{ $site->path }}</flux:text>
                        <flux:button wire:click="openInFinder" size="xs" variant="ghost" icon="folder-open" :disabled="$this->isProjectMissingOnDisk()" />
                    </div>
                </div>
            </div>

            @if($this->themeScreenshot)
                <div class="space-y-2">
                    <flux:heading size="sm">Theme Preview</flux:heading>
                    <div class="relative rounded-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden aspect-[4/3] bg-zinc-100 dark:bg-zinc-900">
                        <img
                            src="{{ $this->themeScreenshot }}"
                            alt="Theme screenshot"
                            class="absolute inset-0 h-full w-full object-cover object-center"
                        />
                    </div>
                </div>
            @endif
        </div>

        <div class="space-y-4">
            <flux:heading size="sm">Environment</flux:heading>

            <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 divide-y divide-zinc-200 dark:divide-zinc-700">
                <div class="flex justify-between items-center px-4 py-3">
                    <flux:text class="text-sm text-zinc-500">PHP Version</flux:text>
                    <select
                        wire:change="changePhpVersion($event.target.value)"
                        class="text-sm bg-transparent border-0 text-right cursor-pointer focus:ring-0 p-0 dark:text-white"
                        @disabled($actionRunning || $this->isProjectMissingOnDisk())
                    >
                        @foreach($phpVersions as $v)
                            <option value="{{ $v }}" @selected($v === $site->php_version)>{{ $v }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex justify-between items-center px-4 py-3">
                    <flux:text class="text-sm text-zinc-500">MariaDB</flux:text>
                    <select
                        wire:change="changeDbVersion($event.target.value)"
                        class="text-sm bg-transparent border-0 text-right cursor-pointer focus:ring-0 p-0 dark:text-white"
                        @disabled($actionRunning || $this->isProjectMissingOnDisk())
                    >
                        @foreach($dbVersions as $v)
                            <option value="{{ $v }}" @selected($v === $site->db_version)>{{ $v }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex justify-between items-center px-4 py-3">
                    <flux:text class="text-sm text-zinc-500">DB Port</flux:text>
                    <flux:text class="text-sm">{{ $site->db_port ?? config('lando_dev.defaults.database_forward_port_start') }}</flux:text>
                </div>
                <div class="flex justify-between items-center px-4 py-3">
                    <flux:text class="text-sm text-zinc-500">Redis</flux:text>
                    <select
                        wire:change="changeRedisVersion($event.target.value)"
                        class="text-sm bg-transparent border-0 text-right cursor-pointer focus:ring-0 p-0 dark:text-white"
                        @disabled($actionRunning || $this->isProjectMissingOnDisk())
                    >
                        @foreach($redisVersions as $v)
                            <option value="{{ $v }}" @selected($v === $site->redis_version)>{{ $v }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex justify-between items-center px-4 py-3">
                    <flux:text class="text-sm text-zinc-500">Theme</flux:text>
                    @if(count($availableThemes) > 0)
                        <select
                            wire:change="switchTheme($event.target.value)"
                            class="text-sm bg-transparent border-0 text-right cursor-pointer focus:ring-0 p-0 dark:text-white"
                            @disabled($actionRunning || $this->isProjectMissingOnDisk())
                        >
                            @foreach($availableThemes as $theme)
                                <option value="{{ $theme['name'] }}" @selected($theme['name'] === $activeTheme)>
                                    {{ $theme['title'] }}{{ $theme['status'] === 'active' ? ' (active)' : '' }}
                                </option>
                            @endforeach
                        </select>
                    @else
                        <flux:text class="text-sm">{{ $site->theme_name ?? '--' }}</flux:text>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Action Output (show while running so destroy/start/stop stream like create-site steps) --}}
    @if($actionRunning || $actionOutput)
        <div class="mt-6">
            <flux:heading size="sm" class="mb-2">Output</flux:heading>
            <div
                id="terminal-output"
                x-data="{ autoScroll: true }"
                x-init="$watch('autoScroll', () => {})"
                x-intersect:leave="autoScroll = false"
                wire:key="site-dashboard-terminal"
                @landodev-scroll-terminal.window="if (autoScroll) { requestAnimationFrame(() => { $el.scrollTop = $el.scrollHeight }) }"
                @scroll="autoScroll = ($el.scrollTop + $el.clientHeight >= $el.scrollHeight - 50)"
                class="bg-zinc-900 text-green-400 font-mono text-xs p-4 rounded-lg h-72 overflow-y-auto"
            >
                @foreach(explode("\n", $actionOutput) as $line)
                    <span class="terminal-line">{!! $line === '' ? '&nbsp;' : \App\Support\AnsiToHtml::lineToHtml($line) !!}</span>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Change Password Modal --}}
    <flux:modal wire:model="showPasswordModal" class="max-w-sm">
        <form wire:submit="changePassword" class="space-y-4">
            <flux:heading size="lg">Change Admin Password</flux:heading>
            <flux:text>Update the WordPress admin password for <strong>{{ $site->admin_username }}</strong>.</flux:text>

            <flux:field>
                <flux:label>New Password</flux:label>
                <flux:input wire:model="newPassword" type="password" placeholder="Enter new password" />
                <flux:error name="newPassword" />
            </flux:field>

            <div class="flex justify-end gap-3 pt-2">
                <flux:button wire:click="$set('showPasswordModal', false)" variant="ghost">Cancel</flux:button>
                <flux:button type="submit" variant="primary" icon="key">Change Password</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Destroy Confirmation Modal --}}
    <flux:modal wire:model="showDestroyModal" class="max-w-md">
        <div class="space-y-4">
            @if($this->isProjectMissingOnDisk())
                <flux:heading size="lg">Remove stale site?</flux:heading>
                <flux:text>
                    The project folder is already gone. Confirming will only remove <strong>{{ $site->name }}</strong> from this app (database record). Nothing will be deleted from disk.
                </flux:text>
                <div class="p-2 rounded bg-zinc-100 dark:bg-zinc-800 font-mono text-sm">
                    {{ $site->path }}
                </div>
            @else
                <flux:heading size="lg">Destroy Site?</flux:heading>
                <flux:text>
                    This will permanently destroy the Lando containers and <strong>delete all files</strong> at:
                </flux:text>
                <div class="p-2 rounded bg-zinc-100 dark:bg-zinc-800 font-mono text-sm">
                    {{ $site->path }}
                </div>
                <flux:text class="text-red-600 dark:text-red-400 font-medium">
                    This action cannot be undone.
                </flux:text>
            @endif
            <div class="flex justify-end gap-3 pt-2">
                <flux:button wire:click="$set('showDestroyModal', false)" variant="ghost">Cancel</flux:button>
                <flux:button wire:click="destroySite" variant="danger" icon="trash">
                    {{ $this->isProjectMissingOnDisk() ? 'Remove from app' : 'Destroy' }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
