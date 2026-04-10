<div>
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">Import Local Sites</flux:heading>
            <flux:subheading class="mt-1">
                Local Lando projects found in <code class="font-mono text-xs">{{ app(\App\Services\PlatformDetector::class)->defaultCodePath() }}</code> that are not yet tracked in Lando Studio.
            </flux:subheading>
        </div>
        <flux:button href="{{ route('settings', [], false) }}" wire:navigate variant="ghost" icon="arrow-left" size="sm">
            Back to Settings
        </flux:button>
    </div>

    <div class="mt-8">
        @if(count($candidates) === 0)
            <div class="p-10 rounded-xl border-2 border-dashed border-zinc-300 dark:border-zinc-600 text-center">
                <flux:icon name="check-circle" class="w-12 h-12 mx-auto text-green-500 mb-3" />
                <flux:heading size="lg">All caught up!</flux:heading>
                <flux:text class="mt-1 text-zinc-500">Every Lando project in your sites folder is already listed in Lando Studio.</flux:text>
            </div>
        @else
            <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50">
                            <th class="px-4 py-3 text-left font-medium text-zinc-500 dark:text-zinc-400">Site</th>
                            <th class="px-4 py-3 text-left font-medium text-zinc-500 dark:text-zinc-400">Path</th>
                            <th class="px-4 py-3 text-left font-medium text-zinc-500 dark:text-zinc-400">PHP</th>
                            <th class="px-4 py-3 text-left font-medium text-zinc-500 dark:text-zinc-400">Database</th>
                            <th class="px-4 py-3 text-left font-medium text-zinc-500 dark:text-zinc-400">Redis</th>
                            <th class="px-4 py-3 text-right font-medium text-zinc-500 dark:text-zinc-400">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700/60">
                        @foreach($candidates as $site)
                            <tr wire:key="{{ $site['name'] }}" class="hover:bg-zinc-50 dark:hover:bg-zinc-800/30 transition-colors">
                                <td class="px-4 py-3 font-medium text-zinc-900 dark:text-zinc-100">
                                    {{ $site['name'] }}
                                </td>
                                <td class="px-4 py-3 text-zinc-500 dark:text-zinc-400 font-mono text-xs truncate max-w-[220px]" title="{{ $site['path'] }}">
                                    {{ $site['path'] }}
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">
                                        PHP {{ $site['php_version'] }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">
                                        {{ $site['db_type'] }} {{ $site['db_version'] }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    @if($site['redis_version'])
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300">
                                            Redis {{ $site['redis_version'] }}
                                        </span>
                                    @else
                                        <span class="text-zinc-400 dark:text-zinc-500 text-xs">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <flux:button
                                        wire:click="import('{{ $site['name'] }}')"
                                        wire:loading.attr="disabled"
                                        wire:target="import('{{ $site['name'] }}')"
                                        size="sm"
                                        variant="primary"
                                        icon="arrow-down-tray"
                                    >
                                        <span wire:loading.remove wire:target="import('{{ $site['name'] }}')">Import</span>
                                        <span wire:loading wire:target="import('{{ $site['name'] }}')">Importing…</span>
                                    </flux:button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <flux:text class="mt-4 text-xs text-zinc-400 dark:text-zinc-500">
                {{ count($candidates) }} {{ Str::plural('site', count($candidates)) }} found · Importing adds them to the sidebar without modifying any files on disk.
            </flux:text>
        @endif
    </div>
</div>
