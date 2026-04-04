<div>
    <flux:heading size="xl">Create Site</flux:heading>
    <flux:subheading class="mt-1">Choose how you want to create your site</flux:subheading>

    <div class="mt-8 grid grid-cols-2 gap-6 max-w-2xl">
        <a href="{{ route('create.new', [], false) }}" wire:navigate
           class="flex flex-col items-center justify-center gap-4 p-8 rounded-xl border-2 border-zinc-200 dark:border-zinc-700 hover:border-blue-500 dark:hover:border-blue-500 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-all cursor-pointer group">
            <div class="w-16 h-16 rounded-full bg-blue-100 dark:bg-blue-900/50 flex items-center justify-center group-hover:bg-blue-200 dark:group-hover:bg-blue-800/50 transition-colors">
                <flux:icon name="plus" class="w-8 h-8 text-blue-600" />
            </div>
            <div class="text-center">
                <flux:heading size="lg">New Website</flux:heading>
                <flux:text class="mt-1 text-zinc-500">Create a fresh WordPress site with Sage theme</flux:text>
            </div>
        </a>

        <a href="{{ route('create.clone', [], false) }}" wire:navigate
           class="flex flex-col items-center justify-center gap-4 p-8 rounded-xl border-2 border-zinc-200 dark:border-zinc-700 hover:border-amber-500 dark:hover:border-amber-500 hover:bg-amber-50 dark:hover:bg-amber-900/20 transition-all cursor-pointer group">
            <div class="w-16 h-16 rounded-full bg-amber-100 dark:bg-amber-900/50 flex items-center justify-center group-hover:bg-amber-200 dark:group-hover:bg-amber-800/50 transition-colors">
                <flux:icon name="arrow-down-tray" class="w-8 h-8 text-amber-600" />
            </div>
            <div class="text-center">
                <flux:heading size="lg">Clone Existing</flux:heading>
                <flux:text class="mt-1 text-zinc-500">Clone from a production WordPress site</flux:text>
            </div>
        </a>
    </div>
</div>
