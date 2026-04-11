<div @if($actionRunning || $isExecuting) wire:poll.3s="pollActionStatus" @endif>
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
        <flux:button wire:click="confirmDestroy" variant="danger" size="sm" icon="trash" :disabled="$actionRunning || $isExecuting">
            Destroy
        </flux:button>
        @if($site->remote_site_id)
        <flux:button
            wire:click="confirmSync"
            size="sm"
            icon="arrow-down-tray"
            variant="filled"
            :disabled="$actionRunning || $isExecuting || $syncConnectionCheckInProgress"
        >
            @if($syncConnectionCheckInProgress)
                <flux:icon name="arrow-path" class="w-4 h-4 animate-spin" />
                Checking…
            @else
                Sync DB
            @endif
        </flux:button>
        @endif
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

    {{-- Sync terminal (WithCommandExecution step-based output) --}}
    @if($isExecuting || $terminalOutput)
        <div class="mt-6">
            <div class="flex items-center gap-2 mb-2">
                <flux:heading size="sm">Sync Output</flux:heading>
                @if($isExecuting)
                    <div class="flex items-center gap-1.5 text-blue-500 dark:text-blue-400">
                        <flux:icon name="arrow-path" class="w-3.5 h-3.5 animate-spin" />
                        <flux:text class="text-xs">Step {{ $currentStep + 1 }} of {{ $totalSteps }}: {{ $steps[$currentStep]['label'] ?? '' }}</flux:text>
                    </div>
                @endif
            </div>
            <div class="mb-3 flex flex-wrap gap-2">
                @foreach($steps as $i => $step)
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium
                        {{ $step['status'] === 'completed' ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' :
                           ($step['status'] === 'running' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300 animate-pulse' :
                           ($step['status'] === 'failed' ? 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300' :
                           'bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400')) }}">
                        {{ $step['label'] }}
                    </span>
                @endforeach
            </div>
            <div
                id="sync-terminal-output"
                x-data="{ autoScroll: true }"
                wire:key="site-sync-terminal"
                @landodev-scroll-terminal.window="if (autoScroll) { requestAnimationFrame(() => { $el.scrollTop = $el.scrollHeight }) }"
                @scroll="autoScroll = ($el.scrollTop + $el.clientHeight >= $el.scrollHeight - 50)"
                class="bg-zinc-900 text-green-400 font-mono text-xs p-4 rounded-lg h-72 overflow-y-auto"
            >
                @foreach(explode("\n", $terminalOutput ?: 'Preparing sync…') as $line)
                    <span class="block terminal-line">{!! $line === '' ? '&nbsp;' : \App\Support\AnsiToHtml::lineToHtml($line) !!}</span>
                @endforeach
            </div>
            @if($executionFailed)
                <div class="mt-3 flex items-center gap-3">
                    <flux:text class="text-sm text-red-600 dark:text-red-400">Sync failed at step {{ $currentStep + 1 }}.</flux:text>
                    <flux:button wire:click="retryFromFailedStep" size="sm" variant="primary" icon="arrow-path">Retry step</flux:button>
                </div>
            @endif
        </div>
    @endif

    {{-- Remote Site Link card --}}
    @if(!$this->isProjectMissingOnDisk())
    <div class="mt-8 p-4 rounded-xl border border-zinc-200 dark:border-zinc-700">
        <div class="flex items-center justify-between gap-4 flex-wrap">
            <div>
                <flux:heading size="sm">Production Remote</flux:heading>
                <flux:text class="text-sm text-zinc-500 mt-0.5">
                    @if($site->remote_site_id)
                        Linked to <strong>{{ $site->remoteSite?->remote_domain ?? 'unknown' }}</strong>
                    @else
                        Not linked to any remote site — link one to enable DB sync.
                    @endif
                </flux:text>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <select
                    wire:model.live="selectedRemoteSiteId"
                    class="text-sm rounded-lg border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-800 px-3 py-1.5 focus:ring-2 focus:ring-blue-500 focus:outline-none"
                >
                    <option value="">— unlinked —</option>
                    @foreach($this->remoteSites as $remote)
                        <option value="{{ $remote->id }}" @selected($selectedRemoteSiteId == $remote->id)>
                            {{ $remote->remote_domain }}
                        </option>
                    @endforeach
                </select>
                <flux:button wire:click="linkRemoteSite" size="sm" variant="primary" icon="link">
                    {{ $selectedRemoteSiteId ? 'Save link' : 'Unlink' }}
                </flux:button>
                <flux:button wire:click="openNewRemoteModal" size="sm" variant="ghost" icon="plus" title="Create new remote site">
                    New
                </flux:button>
                @if($site->remote_site_id)
                    <flux:button wire:click="unlinkRemoteSite" size="sm" variant="ghost" icon="x-mark" />
                @endif
            </div>
        </div>
    </div>
    @endif

    {{-- New Remote Site Modal --}}
    <flux:modal wire:model="showNewRemoteModal" class="max-w-xl">
        <form wire:submit="saveNewRemoteSite" class="space-y-4">
            <flux:heading size="lg">Add New Remote Site</flux:heading>
            <flux:text>Fill in the production server details to create a remote record and link it to <strong>{{ $site->name }}</strong>.</flux:text>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:field class="sm:col-span-2">
                    <flux:label>Production domain</flux:label>
                    <flux:input wire:model="newRemoteDomain" placeholder="example.com" />
                    <flux:error name="newRemoteDomain" />
                </flux:field>

                <flux:field>
                    <flux:label>SSH server IP / hostname</flux:label>
                    <flux:input wire:model="newRemoteSshIp" placeholder="1.2.3.4" />
                    <flux:error name="newRemoteSshIp" />
                </flux:field>

                <flux:field>
                    <flux:label>SSH user</flux:label>
                    <flux:input wire:model="newRemoteSshUser" placeholder="forge" />
                    <flux:error name="newRemoteSshUser" />
                </flux:field>

                <flux:field class="sm:col-span-2">
                    <flux:label>SSH password / key passphrase</flux:label>
                    <flux:description>Leave blank if using key-based auth without a passphrase.</flux:description>
                    <flux:input wire:model="newRemoteSshPassword" type="password" placeholder="(optional)" />
                </flux:field>

                <flux:field>
                    <flux:label>Database name</flux:label>
                    <flux:input wire:model="newRemoteDbName" placeholder="wp_production" />
                    <flux:error name="newRemoteDbName" />
                </flux:field>

                <flux:field>
                    <flux:label>Database user</flux:label>
                    <flux:input wire:model="newRemoteDbUser" placeholder="wp_user" />
                    <flux:error name="newRemoteDbUser" />
                </flux:field>

                <flux:field class="sm:col-span-2">
                    <flux:label>Database password</flux:label>
                    <flux:input wire:model="newRemoteDbPassword" type="password" placeholder="(optional)" />
                </flux:field>

                <flux:field>
                    <flux:label>Theme folder name</flux:label>
                    <flux:description>Production theme folder, used for search-replace during sync.</flux:description>
                    <flux:input wire:model="newRemoteThemeName" placeholder="my-theme" />
                </flux:field>

                <flux:field>
                    <flux:label>Remote WordPress path</flux:label>
                    <flux:description>Absolute path on the server (e.g. <code>/var/www/html</code>).</flux:description>
                    <flux:input wire:model="newRemotePath" placeholder="/var/www/html" />
                </flux:field>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <flux:button wire:click="$set('showNewRemoteModal', false)" variant="ghost">Cancel</flux:button>
                <flux:button type="submit" variant="primary" icon="server">Save &amp; Link</flux:button>
            </div>
        </form>
    </flux:modal>

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

    {{-- Sync Confirmation Modal --}}
    <flux:modal wire:model="showSyncModal" class="max-w-lg">
        <div class="space-y-4">
            <flux:heading size="lg">Sync Database from Production</flux:heading>
            <flux:text>
                This will pull a fresh database dump from
                <strong>{{ $site->remoteSite?->remote_domain ?? 'production' }}</strong>
                and import it into your local <strong>{{ $site->name }}</strong> installation.
            </flux:text>
            <div class="p-3 rounded bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 text-sm text-amber-800 dark:text-amber-300">
                ⚠️ Your local database will be <strong>overwritten</strong>. This cannot be undone.
            </div>
            <flux:field>
                <flux:label>Local theme folder name</flux:label>
                <flux:description>If your local theme folder name differs from production, enter it here so the search-replace is correct.</flux:description>
                <flux:input
                    wire:model="syncLocalTheme"
                    placeholder="{{ $site->theme_name ?? 'theme-folder-name' }}"
                />
            </flux:field>
            <div class="flex justify-end gap-3 pt-2">
                <flux:button wire:click="$set('showSyncModal', false)" variant="ghost">Cancel</flux:button>
                <flux:button wire:click="startSync" variant="primary" icon="arrow-down-tray">
                    Sync Now
                </flux:button>
            </div>
        </div>
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
