<div>
    <flux:heading size="xl">Settings</flux:heading>
    <flux:subheading class="mt-1">Configure LandoDEV defaults and remote sites</flux:subheading>

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

    {{-- Remote Sites Section --}}
    <div class="mt-10">
        <div class="flex items-center justify-between">
            <flux:heading size="lg">Remote Sites</flux:heading>
            <flux:button wire:click="addRemoteSite" size="sm" icon="plus" variant="primary">
                Add Remote Site
            </flux:button>
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
                            <flux:label>Local Domain (auto-generated if blank)</flux:label>
                            <flux:input wire:model="localDomain" placeholder="https://example.lndo.site" />
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
                            <flux:input wire:model="repoUrl" placeholder="https://github.com/..." />
                        </flux:field>
                    </div>

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
                                <flux:text class="text-sm text-zinc-500">
                                    {{ $remote->ssh_user }}@{{ $remote->ssh_server_ip }}
                                    @if($remote->theme_name)
                                        &middot; Theme: {{ $remote->theme_name }}
                                    @endif
                                </flux:text>
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
</div>
