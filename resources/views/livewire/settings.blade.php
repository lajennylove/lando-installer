<div @if($landoUpdating) wire:poll.3s="pollLandoUpdate" @endif>
    <flux:heading size="xl">Settings</flux:heading>
    <flux:subheading class="mt-1">Configure Lando Studio defaults and remote sites</flux:subheading>

    {{-- Appearance Section --}}
    <div class="mt-8">
        <flux:heading size="lg">Appearance</flux:heading>
        <flux:subheading class="mt-1">Choose how Lando Studio looks on your screen</flux:subheading>

        <div class="mt-4 flex gap-3">
            {{-- Auto --}}
            <button
                wire:click="setAppearance('system')"
                @class([
                    'flex flex-col items-center gap-2 rounded-xl border px-6 py-4 text-sm font-medium transition cursor-pointer focus:outline-none',
                    'ring-2 ring-zinc-900 dark:ring-white border-zinc-300 dark:border-zinc-500 bg-zinc-100 dark:bg-zinc-700 text-zinc-900 dark:text-white' => $appearance === 'system',
                    'border-zinc-200 dark:border-zinc-700 hover:border-zinc-300 dark:hover:border-zinc-500 text-zinc-600 dark:text-zinc-400' => $appearance !== 'system',
                ])
            >
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="12" cy="12" r="9" />
                    <path d="M12 3v18" />
                    <line x1="16.24" y1="7.76" x2="17.66" y2="6.34" />
                    <line x1="21" y1="12" x2="22.42" y2="12" />
                    <line x1="16.24" y1="16.24" x2="17.66" y2="17.66" />
                    <path d="M12 7a5 5 0 0 0 0 10" fill="currentColor" opacity="0.2" />
                </svg>
                Auto
            </button>

            {{-- Light --}}
            <button
                wire:click="setAppearance('light')"
                @class([
                    'flex flex-col items-center gap-2 rounded-xl border px-6 py-4 text-sm font-medium transition cursor-pointer focus:outline-none',
                    'ring-2 ring-zinc-900 dark:ring-white border-zinc-300 dark:border-zinc-500 bg-zinc-100 dark:bg-zinc-700 text-zinc-900 dark:text-white' => $appearance === 'light',
                    'border-zinc-200 dark:border-zinc-700 hover:border-zinc-300 dark:hover:border-zinc-500 text-zinc-600 dark:text-zinc-400' => $appearance !== 'light',
                ])
            >
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="12" cy="12" r="4" />
                    <line x1="12" y1="2" x2="12" y2="4" /><line x1="12" y1="20" x2="12" y2="22" />
                    <line x1="4.22" y1="4.22" x2="5.64" y2="5.64" /><line x1="18.36" y1="18.36" x2="19.78" y2="19.78" />
                    <line x1="2" y1="12" x2="4" y2="12" /><line x1="20" y1="12" x2="22" y2="12" />
                    <line x1="4.22" y1="19.78" x2="5.64" y2="18.36" /><line x1="18.36" y1="5.64" x2="19.78" y2="4.22" />
                </svg>
                Light
            </button>

            {{-- Dark --}}
            <button
                wire:click="setAppearance('dark')"
                @class([
                    'flex flex-col items-center gap-2 rounded-xl border px-6 py-4 text-sm font-medium transition cursor-pointer focus:outline-none',
                    'ring-2 ring-zinc-900 dark:ring-white border-zinc-300 dark:border-zinc-500 bg-zinc-100 dark:bg-zinc-700 text-zinc-900 dark:text-white' => $appearance === 'dark',
                    'border-zinc-200 dark:border-zinc-700 hover:border-zinc-300 dark:hover:border-zinc-500 text-zinc-600 dark:text-zinc-400' => $appearance !== 'dark',
                ])
            >
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" />
                </svg>
                Dark
            </button>
        </div>
    </div>

    {{-- Defaults Section --}}
    <div class="mt-8">
        <flux:heading size="lg">Default Versions for New Sites</flux:heading>
        <flux:subheading class="mt-1">These will be used when creating new sites</flux:subheading>

        <div class="mt-4 rounded-lg border border-zinc-200 dark:border-zinc-700 divide-y divide-zinc-200 dark:divide-zinc-700">
            <div class="flex justify-between items-center px-4 py-3">
                <flux:text class="text-sm text-zinc-500">Default Code Path</flux:text>
                <div class="flex items-center gap-2">
                    <input
                        wire:model.blur="defaultCodePath"
                        wire:change="saveDefaultCodePath"
                        type="text"
                        class="text-sm bg-transparent border-0 text-right focus:ring-0 p-0 font-mono w-48 dark:text-white"
                    />
                </div>
            </div>
            <div class="flex justify-between items-center px-4 py-3">
                <flux:text class="text-sm text-zinc-500">PHP Version</flux:text>
                <select
                    wire:change="saveDefaultPhpVersion($event.target.value)"
                    class="text-sm bg-transparent border-0 text-right cursor-pointer focus:ring-0 p-0 dark:text-white"
                >
                    @foreach($phpVersions as $v)
                        <option value="{{ $v }}" @selected($v === $defaultPhpVersion)>{{ $v }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex justify-between items-center px-4 py-3">
                <flux:text class="text-sm text-zinc-500">MariaDB Version</flux:text>
                <select
                    wire:change="saveDefaultDbVersion($event.target.value)"
                    class="text-sm bg-transparent border-0 text-right cursor-pointer focus:ring-0 p-0 dark:text-white"
                >
                    @foreach($dbVersions as $v)
                        <option value="{{ $v }}" @selected($v === $defaultDbVersion)>{{ $v }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex justify-between items-center px-4 py-3">
                <flux:text class="text-sm text-zinc-500">Redis Version</flux:text>
                <select
                    wire:change="saveDefaultRedisVersion($event.target.value)"
                    class="text-sm bg-transparent border-0 text-right cursor-pointer focus:ring-0 p-0 dark:text-white"
                >
                    @foreach($redisVersions as $v)
                        <option value="{{ $v }}" @selected($v === $defaultRedisVersion)>{{ $v }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    {{-- System Dependencies --}}
    <div class="mt-10">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="lg">System Dependencies</flux:heading>
                <flux:subheading class="mt-1">Lando and Docker are required to run local sites.</flux:subheading>
            </div>
            <div class="flex items-center gap-2">
                <flux:button
                    wire:click="runLandoUpdate"
                    wire:loading.attr="disabled"
                    wire:target="runLandoUpdate"
                    variant="filled"
                    icon="arrow-up-circle"
                    :disabled="$landoUpdating"
                >
                    @if($landoUpdating)
                        <flux:icon name="arrow-path" class="w-4 h-4 animate-spin" />
                        Updating…
                    @else
                        Update Lando
                    @endif
                </flux:button>
                <flux:button href="{{ route('setup') }}" variant="primary" icon="wrench-screwdriver">
                    Manage Dependencies
                </flux:button>
            </div>
        </div>

        @if($landoUpdating || $landoUpdateOutput !== '')
            <div class="mt-4">
                <div class="flex items-center justify-between mb-2">
                    <flux:text class="text-xs text-zinc-500">Update log</flux:text>
                    @if($landoUpdating)
                        <div class="flex items-center gap-1.5 text-blue-500 dark:text-blue-400">
                            <flux:icon name="arrow-path" class="w-3.5 h-3.5 animate-spin" />
                            <flux:text class="text-xs">Running lando update -y…</flux:text>
                        </div>
                    @endif
                </div>
                <div
                    id="lando-update-output"
                    x-data="{ autoScroll: true }"
                    @landodev-scroll-terminal.window="if (autoScroll) { requestAnimationFrame(() => { $el.scrollTop = $el.scrollHeight }) }"
                    @scroll="autoScroll = ($el.scrollTop + $el.clientHeight >= $el.scrollHeight - 50)"
                    class="bg-zinc-900 text-green-400 font-mono text-xs p-4 rounded-lg overflow-y-auto max-h-64"
                >
                    @foreach(explode("\n", $landoUpdateOutput ?: 'Starting…') as $line)
                        <span class="block">{!! $line === '' ? '&nbsp;' : \App\Support\AnsiToHtml::lineToHtml($line) !!}</span>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    {{-- Application data (SQLite) — sidebar reads sites table; deleting folders/Docker does not remove rows --}}
    <div class="mt-10">
        <flux:heading size="lg">Application data</flux:heading>
        <flux:subheading class="mt-1">
            Local sites in the sidebar are stored in this app’s SQLite database. Removing project folders or Lando containers does not remove those rows.
            In local dev, the desktop app and <code class="font-mono text-xs">php artisan</code> normally use the same file; release builds may also use a database under Application Support. Reset clears every distinct file listed below.
        </flux:subheading>
        <div class="mt-3 space-y-1 rounded-lg border border-zinc-200 dark:border-zinc-600 bg-zinc-50 dark:bg-zinc-900/40 p-3 font-mono text-xs text-zinc-600 dark:text-zinc-400">
            @foreach($this->databaseLocationRows() as $row)
                <div>
                    <span class="font-semibold text-zinc-800 dark:text-zinc-200">{{ $row['connection'] }}</span>
                    @if($row['path'] !== '')
                        <span class="break-all"> — {{ $row['path'] }}</span>
                    @endif
                    @if(array_key_exists('site_count', $row) && $row['site_count'] !== null)
                        <span> ({{ $row['site_count'] }} {{ $row['site_count'] === 1 ? 'site' : 'sites' }})</span>
                    @endif
                    @if(! empty($row['error']))
                        <span class="text-red-600 dark:text-red-400"> — {{ $row['error'] }}</span>
                    @endif
                </div>
            @endforeach
        </div>
        <div class="mt-4 rounded-lg border border-red-200 dark:border-red-900/50 bg-red-50/50 dark:bg-red-950/20 p-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <flux:text class="text-sm text-zinc-600 dark:text-zinc-300">
                    Clear all local sites, command history, remote site presets, and sessions. Version defaults above are kept.
                </flux:text>
                <flux:button wire:click="$set('showResetAppDataModal', true)" variant="danger" icon="trash">
                    Reset application data
                </flux:button>
            </div>
        </div>
    </div>

    {{-- Import Local Sites --}}
    <div class="mt-10">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="lg">Local Installations</flux:heading>
                <flux:subheading class="mt-1">Import Lando projects that exist on disk but are not yet tracked in Lando Studio.</flux:subheading>
            </div>
            <flux:button href="{{ route('settings.import', [], false) }}" wire:navigate variant="primary" icon="arrow-down-tray">
                Import local installations
            </flux:button>
        </div>
    </div>

    {{-- Remote Sites Section --}}
    <div class="mt-10">
        <div class="flex items-center justify-between">
            <flux:heading size="lg">Remote Sites</flux:heading>
            <div class="flex items-center gap-2">
                <flux:button wire:click="openBatchModal" size="sm" icon="arrow-path" variant="ghost">
                    Batch import/export
                </flux:button>
                <flux:button wire:click="addRemoteSite" size="sm" icon="plus" variant="primary">
                    Add Remote Site
                </flux:button>
            </div>
        </div>

        @if($showRemoteSiteForm)
            <div class="mt-4 p-6 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50">
                <flux:heading size="sm" class="mb-4">
                    {{ $editingRemoteSiteId ? 'Edit' : 'Add' }} Remote Site
                </flux:heading>

                <form wire:submit="saveRemoteSite" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <flux:field>
                            <flux:label>Remote Domain</flux:label>
                            <flux:input wire:model="remoteDomain" placeholder="https://example.com" />
                            <flux:error name="remoteDomain" />
                        </flux:field>
                        <flux:field>
                            <flux:label for="remote-local-site-name">Local site name</flux:label>
                            <div
                                class="flex h-10 items-stretch overflow-hidden rounded-lg shadow-xs border border-zinc-200 border-b-zinc-300/80 bg-white divide-x divide-zinc-200 dark:divide-white/10 dark:border-white/10 dark:border-b-white/5 dark:bg-white/10 focus-within:ring-2 focus-within:ring-zinc-400/30 focus-within:border-zinc-300 dark:focus-within:ring-white/20 dark:focus-within:border-white/20"
                            >
                                <span
                                    class="inline-flex items-center px-3 text-xs font-mono text-zinc-500 bg-zinc-50 select-none sm:text-sm dark:text-zinc-400 dark:bg-zinc-900/50"
                                    aria-hidden="true"
                                >https://</span>
                                <input
                                    id="remote-local-site-name"
                                    type="text"
                                    name="localSiteName"
                                    wire:model="localSiteName"
                                    placeholder="my-site"
                                    autocomplete="off"
                                    class="min-w-0 flex-1 border-0 bg-transparent px-3 text-sm text-zinc-700 placeholder-zinc-400 focus:ring-0 dark:text-zinc-200 dark:placeholder-zinc-500"
                                />
                                <span
                                    class="inline-flex items-center px-3 text-xs font-mono text-zinc-500 bg-zinc-50 select-none sm:text-sm dark:text-zinc-400 dark:bg-zinc-900/50"
                                    aria-hidden="true"
                                >.lndo.site</span>
                            </div>
                            <flux:description>
                                Optional. The name is slugified when saved (HTTPS). Leave blank if you do not need a preset.
                            </flux:description>
                            <flux:error name="localSiteName" />
                        </flux:field>
                    </div>

                    <flux:separator />
                    <flux:heading size="xs">SSH Access</flux:heading>

                    <div class="grid grid-cols-3 gap-4">
                        <flux:field>
                            <flux:label>SSH Server IP</flux:label>
                            <flux:input wire:model="sshServerIp" placeholder="192.168.1.1" />
                            <flux:error name="sshServerIp" />
                        </flux:field>
                        <flux:field>
                            <flux:label>SSH User</flux:label>
                            <flux:input wire:model="sshUser" />
                            <flux:error name="sshUser" />
                        </flux:field>
                        <flux:field>
                            <flux:label>SSH Password</flux:label>
                            <flux:input wire:model="sshPassword" type="password" />
                        </flux:field>
                    </div>

                    <flux:field>
                        <flux:label>WordPress root path (on server)</flux:label>
                        <flux:input
                            wire:model="remotePath"
                            placeholder="/home/master/applications/pphhzzudbv/public_html"
                            class="font-mono text-sm"
                        />
                        <flux:description>
                            Absolute path to the folder that contains <code class="text-xs">wp-config.php</code> (run <code class="text-xs">pwd</code> after <code class="text-xs">cd …/public_html</code>). Used when syncing plugins from the server.
                        </flux:description>
                        <flux:error name="remotePath" />
                    </flux:field>

                    <flux:separator />
                    <flux:heading size="xs">Database</flux:heading>

                    <div class="grid grid-cols-3 gap-4">
                        <flux:field>
                            <flux:label>DB Name</flux:label>
                            <flux:input wire:model="dbName" />
                            <flux:error name="dbName" />
                        </flux:field>
                        <flux:field>
                            <flux:label>DB User</flux:label>
                            <flux:input wire:model="dbUser" />
                            <flux:error name="dbUser" />
                        </flux:field>
                        <flux:field>
                            <flux:label>DB Password</flux:label>
                            <flux:input wire:model="dbPassword" type="password" />
                        </flux:field>
                    </div>

                    <flux:separator />
                    <flux:heading size="xs">Theme</flux:heading>

                    <div class="grid grid-cols-2 gap-4">
                        <flux:field>
                            <flux:label>Theme Name</flux:label>
                            <flux:input wire:model="themeName" placeholder="my-theme" />
                        </flux:field>
                        <flux:field>
                            <flux:label>Git Repo URL</flux:label>
                            <flux:input wire:model.live.debounce.300ms="repoUrl" placeholder="https://github.com/..." />
                        </flux:field>
                    </div>

                    @if(filled(trim($repoUrl)))
                        <div class="space-y-2">
                            <flux:checkbox
                                wire:model="installComposerDependencies"
                                label="Install Composer dependencies"
                            />
                            <flux:checkbox
                                wire:model="installNodeDependencies"
                                label="Install Node dependencies"
                            />
                        </div>
                    @endif

                    <div class="flex items-center gap-3 pt-2">
                        <flux:button type="submit" variant="primary">
                            {{ $editingRemoteSiteId ? 'Update' : 'Add' }} Remote Site
                        </flux:button>
                        <flux:button wire:click="cancelRemoteForm" variant="ghost">Cancel</flux:button>
                    </div>
                </form>
            </div>
        @endif

        {{-- Remote Sites List --}}
        <div class="mt-4">
            @if($this->remoteSites->isEmpty() && !$showRemoteSiteForm)
                <div class="p-6 rounded-lg border-2 border-dashed border-zinc-300 dark:border-zinc-600 text-center">
                    <flux:icon name="server-stack" class="w-10 h-10 mx-auto text-zinc-400 mb-2" />
                    <flux:text class="text-zinc-500">No remote sites configured yet.</flux:text>
                </div>
            @else
                <div class="space-y-2">
                    @foreach($this->remoteSites as $remote)
                        <div class="flex items-center justify-between p-4 rounded-lg border border-zinc-200 dark:border-zinc-700">
                            <div>
                                <flux:heading size="sm">{{ $remote->remote_domain }}</flux:heading>
                                @if($remote->theme_name)
                                    <flux:text class="text-sm text-zinc-500">Theme: {{ $remote->theme_name }}</flux:text>
                                @endif
                            </div>
                            <div class="flex items-center gap-2">
                                <flux:button wire:click="editRemoteSite({{ $remote->id }})" size="sm" variant="ghost" icon="pencil" />
                                <flux:button wire:click="confirmDeleteRemoteSite({{ $remote->id }})" size="sm" variant="ghost" icon="trash" class="text-red-500 hover:text-red-700" />
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- Delete Confirmation Modal --}}
    <flux:modal wire:model="showDeleteModal" class="max-w-sm">
        <div class="space-y-4">
            <flux:heading size="lg">Delete Remote Site?</flux:heading>
            <flux:text>This will remove the remote site configuration. Existing cloned sites will not be affected.</flux:text>
            <div class="flex justify-end gap-3">
                <flux:button wire:click="$set('showDeleteModal', false)" variant="ghost">Cancel</flux:button>
                <flux:button wire:click="deleteRemoteSite" variant="danger" icon="trash">Delete</flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal wire:model="showResetAppDataModal" class="max-w-md">
        <div class="space-y-4">
            <flux:heading size="lg">Reset application data?</flux:heading>
            <flux:text>
                This permanently deletes every row for local sites (sidebar list), command logs, remote site SSH presets, and active sessions.
                It does not run <code class="font-mono text-xs">lando destroy</code> or delete files on disk.
            </flux:text>
            <div class="flex justify-end gap-3">
                <flux:button wire:click="$set('showResetAppDataModal', false)" variant="ghost">Cancel</flux:button>
                <flux:button wire:click="resetApplicationData" variant="danger" icon="trash">Reset data</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Batch Import / Export Modal --}}
    <flux:modal wire:model="showBatchModal" class="w-[80vw]">
        <div class="space-y-4">
            <flux:heading size="lg">Batch Import / Export</flux:heading>

            {{-- Top action cards --}}
            <div class="grid grid-cols-2 gap-4">
                {{-- Export card --}}
                <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 flex flex-col gap-3">
                    <div>
                        <flux:heading size="sm">Export CSV</flux:heading>
                        <flux:text class="text-sm text-zinc-500 mt-1">
                            @if($this->remoteSites->isEmpty())
                                No remotes yet — downloads a demo template you can fill in and import.
                            @else
                                Downloads all {{ $this->remoteSites->count() }} remote site(s) as a comma-separated CSV.
                            @endif
                        </flux:text>
                    </div>
                    <flux:button wire:click="exportRemoteSites" icon="arrow-down-tray" variant="ghost">
                        {{ $this->remoteSites->isEmpty() ? 'Download template' : 'Export CSV' }}
                    </flux:button>
                </div>

                {{-- Import card --}}
                <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 flex flex-col gap-3">
                    <div>
                        <flux:heading size="sm">Import CSV</flux:heading>
                        <flux:text class="text-sm text-zinc-500 mt-1">
                            Upload a comma-separated CSV file. Existing remotes with matching domain will be updated; new ones will be created.
                        </flux:text>
                    </div>
                    <flux:field>
                        <input
                            type="file"
                            accept=".csv,.txt"
                            wire:model="batchCsvFile"
                            class="block w-full text-sm text-zinc-500 file:mr-4 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-zinc-100 file:text-zinc-700 dark:file:bg-zinc-700 dark:file:text-zinc-200 hover:file:bg-zinc-200 dark:hover:file:bg-zinc-600 cursor-pointer"
                        />
                    </flux:field>
                </div>
            </div>

            {{-- Parse error --}}
            @if($batchParseError)
                <div class="p-3 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 text-sm text-red-700 dark:text-red-300">
                    {{ $batchParseErrorMessage }}
                </div>
            @endif

            {{-- Preview table --}}
            @if(count($batchRows) > 0)
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <flux:heading size="sm">Preview ({{ count($batchRows) }} rows)</flux:heading>
                        <flux:button wire:click="executeBatchImport" variant="primary" icon="check" size="sm">
                            Import Selected
                        </flux:button>
                    </div>

                    <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
                        <table class="w-full text-sm">
                            <thead class="bg-zinc-50 dark:bg-zinc-800 text-left">
                                <tr>
                                    <th class="px-3 py-2.5 w-8">
                                        <input
                                            type="checkbox"
                                            wire:model.live="batchSelectAll"
                                            wire:change="toggleBatchSelectAll"
                                            class="rounded border-zinc-300 dark:border-zinc-600"
                                        />
                                    </th>
                                    <th class="px-3 py-2.5 font-medium text-zinc-700 dark:text-zinc-300">Domain</th>
                                    <th class="px-3 py-2.5 font-medium text-zinc-700 dark:text-zinc-300">SSH IP</th>
                                    <th class="px-3 py-2.5 font-medium text-zinc-700 dark:text-zinc-300">SSH User</th>
                                    <th class="px-3 py-2.5 font-medium text-zinc-700 dark:text-zinc-300">DB Name</th>
                                    <th class="px-3 py-2.5 font-medium text-zinc-700 dark:text-zinc-300">DB User</th>
                                    <th class="px-3 py-2.5 font-medium text-zinc-700 dark:text-zinc-300">Theme</th>
                                    <th class="px-3 py-2.5 font-medium text-zinc-700 dark:text-zinc-300">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700/50">
                                @foreach($batchRows as $i => $row)
                                    <tr class="{{ $row['exists'] ? 'bg-amber-50 dark:bg-amber-900/10' : '' }}">
                                        <td class="px-3 py-2">
                                            <input
                                                type="checkbox"
                                                wire:model.live="batchRows.{{ $i }}.selected"
                                                class="rounded border-zinc-300 dark:border-zinc-600"
                                            />
                                        </td>
                                        <td class="px-3 py-2 font-medium">
                                            {{ $row['data']['remote_domain'] }}
                                        </td>
                                        <td class="px-3 py-2 text-zinc-600 dark:text-zinc-400 font-mono text-xs">
                                            {{ $row['data']['ssh_server_ip'] }}
                                        </td>
                                        <td class="px-3 py-2 text-zinc-600 dark:text-zinc-400">
                                            {{ $row['data']['ssh_user'] }}
                                        </td>
                                        <td class="px-3 py-2 text-zinc-600 dark:text-zinc-400">
                                            {{ $row['data']['db_name'] }}
                                        </td>
                                        <td class="px-3 py-2 text-zinc-600 dark:text-zinc-400">
                                            {{ $row['data']['db_user'] }}
                                        </td>
                                        <td class="px-3 py-2 text-zinc-600 dark:text-zinc-400">
                                            {{ $row['data']['theme_name'] ?: '—' }}
                                        </td>
                                        <td class="px-3 py-2">
                                            @if($row['exists'])
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">
                                                    ⚠ Update
                                                </span>
                                                @if(count($row['changedFields']) > 0)
                                                    <div class="mt-1 text-xs text-amber-600 dark:text-amber-400">
                                                        Changes:
                                                        @foreach($row['changedFields'] as $field)
                                                            <span class="inline-block bg-amber-100 dark:bg-amber-900/30 rounded px-1 mr-0.5">
                                                                {{ in_array($field, ['ssh_password', 'db_password']) ? str_replace('_', ' ', $field).' [will be updated]' : str_replace('_', ' ', $field) }}
                                                            </span>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            @else
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300">
                                                    ✓ New
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="flex justify-between items-center mt-3">
                        <flux:text class="text-xs text-zinc-500">
                            {{ collect($batchRows)->where('selected', true)->count() }} of {{ count($batchRows) }} selected
                        </flux:text>
                        <flux:button wire:click="executeBatchImport" variant="primary" icon="check">
                            Import Selected
                        </flux:button>
                    </div>
                </div>
            @endif
        </div>
    </flux:modal>
</div>
