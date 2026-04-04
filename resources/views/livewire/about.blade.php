<div>
    <div class="max-w-2xl mx-auto">
        <flux:heading size="xl">About LandoDEV</flux:heading>
        <flux:subheading class="mt-1">Local WordPress Development Manager</flux:subheading>

        <div class="mt-8 space-y-6">
            <div class="flex items-center gap-4">
                <div class="app-logo-frame shrink-0">
                    <img src="/assets/rocket.svg" alt="LandoDEV" class="app-logo-img" />
                </div>
                <div>
                    <flux:heading size="lg">LandoDEV</flux:heading>
                    <flux:text class="text-zinc-500">Version {{ config('nativephp.version', '1.0.0') }}</flux:text>
                </div>
            </div>

            <flux:separator />

            <div class="space-y-4">
                <flux:text>
                    LandoDEV is a desktop application for managing local WordPress development environments
                    powered by Lando. It streamlines the process of creating new WordPress sites with Sage themes
                    and cloning existing production environments.
                </flux:text>

                <flux:heading size="sm" class="mt-4">Stack</flux:heading>
                <div class="grid grid-cols-2 gap-2 text-sm">
                    <flux:text>PHP {{ config('lando_dev.defaults.php_version') }}</flux:text>
                    <flux:text>MariaDB {{ config('lando_dev.defaults.db_version') }}</flux:text>
                    <flux:text>Redis {{ config('lando_dev.defaults.redis_version') }}</flux:text>
                    <flux:text>WordPress + Sage</flux:text>
                </div>

                <flux:heading size="sm" class="mt-4">Built With</flux:heading>
                <div class="grid grid-cols-2 gap-2 text-sm">
                    <flux:text>Laravel + Livewire</flux:text>
                    <flux:text>NativePHP (Electron)</flux:text>
                    <flux:text>Flux UI</flux:text>
                    <flux:text>Tailwind CSS</flux:text>
                </div>
            </div>
        </div>
    </div>
</div>
