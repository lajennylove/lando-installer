<?php

namespace App\Services;

class LandoService
{
    private ?string $landoPath = null;

    public function __construct(
        private PlatformDetector $platform,
        private DependencyChecker $checker,
    ) {}

    public function getLandoPath(): string
    {
        if ($this->landoPath === null) {
            $this->landoPath = $this->checker->getLandoPath() ?? 'lando';
        }

        return $this->landoPath;
    }

    public function start(string $path): string
    {
        return $this->buildCommand($path, 'start');
    }

    public function stop(string $path): string
    {
        return $this->buildCommand($path, 'stop');
    }

    public function restart(string $path): string
    {
        return $this->buildCommand($path, 'restart');
    }

    public function rebuild(string $path): string
    {
        return $this->buildCommand($path, 'rebuild -y');
    }

    public function destroy(string $path): string
    {
        return $this->buildCommand($path, 'destroy -y');
    }

    public function wpConfigCreate(string $path): string
    {
        $dbName = config('lando_dev.defaults.db_name');
        $dbUser = config('lando_dev.defaults.db_user');
        $dbPass = config('lando_dev.defaults.db_password');
        $dbHost = config('lando_dev.defaults.db_host');

        return $this->buildCommand($path,
            "wp config create --dbname={$dbName} --dbuser={$dbUser} --dbpass={$dbPass} --dbhost={$dbHost} --path=wp"
        );
    }

    public function wpCoreInstall(string $path, string $siteName, string $adminUser, string $adminPass, string $adminEmail): string
    {
        $url = 'https://'.$siteName.'.lndo.site';
        $title = ucwords(str_replace('-', ' ', $siteName));

        $urlArg = escapeshellarg($url);
        $titleArg = escapeshellarg($title);
        $userArg = escapeshellarg($adminUser);
        $passArg = escapeshellarg($adminPass);
        $emailArg = escapeshellarg($adminEmail);

        return $this->buildCommand($path,
            "wp core install --url={$urlArg} --title={$titleArg} --admin_user={$userArg} --admin_password={$passArg} --admin_email={$emailArg} --path=wp"
        );
    }

    public function wpCoreDownload(string $path): string
    {
        return $this->buildCommand($path, 'wp core download --path=./wp');
    }

    public function wpSearchReplace(string $path, string $from, string $to): string
    {
        return $this->buildCommand($path, "wp search-replace '{$from}' '{$to}' --all-tables --path=wp");
    }

    public function wpThemeActivate(string $path, string $themeName): string
    {
        return $this->buildCommand($path, "wp theme activate {$themeName} --path=wp");
    }

    public function wpThemeList(string $path): string
    {
        return $this->buildCommand($path, 'wp theme list --format=json --path=wp');
    }

    public function wpUserUpdatePassword(string $path, string $username, string $password): string
    {
        $escapedPass = str_replace("'", "'\\''", $password);

        return $this->buildCommand($path, "wp user update {$username} --user_pass='{$escapedPass}' --path=wp");
    }

    public function dbImport(string $path, string $dumpFile): string
    {
        return $this->buildCommand($path, "db-import {$dumpFile}");
    }

    public function composerCreateProject(string $path, string $package, string $name): string
    {
        $themesPath = "{$path}/wp/wp-content/themes";

        return $this->wrapInShell("cd {$this->escapePath($path)} && {$this->getLandoPath()} ssh -c \"cd /app/wp/wp-content/themes && composer create-project {$package} {$name}\"");
    }

    public function composerInstall(string $path, string $subdir): string
    {
        return $this->wrapInShell("cd {$this->escapePath($path)} && {$this->getLandoPath()} ssh -c \"cd /app/{$subdir} && composer install\"");
    }

    public function yarn(string $path, string $subdir, ?string $command = null): string
    {
        $yarnCmd = $command ? "yarn {$command}" : 'yarn';

        return $this->wrapInShell("cd {$this->escapePath($path)} && {$this->getLandoPath()} ssh -u root -c \"cd /app/{$subdir} && {$yarnCmd}\"");
    }

    public function info(string $path): string
    {
        return $this->buildCommand($path, 'info --format=json');
    }

    public function isRunning(string $path): bool
    {
        $cmd = $this->buildCommand($path, 'info --format=json');
        $output = @shell_exec($cmd.' 2>/dev/null');

        if (! $output) {
            return false;
        }

        $info = json_decode($output, true);
        if (! is_array($info)) {
            return false;
        }

        foreach ($info as $service) {
            if (($service['service'] ?? '') === 'appserver') {
                return ($service['healthy'] ?? false) || str_contains($service['state'] ?? '', 'running');
            }
        }

        return false;
    }

    private function buildCommand(string $path, string $landoCmd): string
    {
        $escapedPath = $this->escapePath($path);

        return $this->wrapInShell("cd {$escapedPath} && {$this->getLandoPath()} {$landoCmd}");
    }

    private function wrapInShell(string $command): string
    {
        return $command;
    }

    private function escapePath(string $path): string
    {
        if ($this->platform->isWindows()) {
            return "\"{$path}\"";
        }

        return "'".str_replace("'", "'\\''", $path)."'";
    }
}
