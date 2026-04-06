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

    /**
     * Same as: lando wp search-replace 'old' 'new' --all-tables --path=wp
     */
    public function wpSearchReplace(string $path, string $from, string $to): string
    {
        $fromArg = escapeshellarg($from);
        $toArg = escapeshellarg($to);

        $cmd = $this->buildCommand($path, "wp search-replace {$fromArg} {$toArg} --all-tables --path=wp");

        // wp search-replace on a large DB runs silently for 2-10+ minutes.
        // Wrap with a heartbeat so the 30s log-idle timer does not fire prematurely.
        return $this->platform->isWindows()
            ? $cmd
            : $this->withHeartbeat('[Lando Studio] Domain replacement in progress', $cmd);
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

    /**
     * Import a gzipped SQL dump into the Lando database service.
     *
     * lando db-import on .gz can pass raw gzip to mysql (ASCII '\\0' / --binary-mode). Decompress
     * to a temporary .sql in the app root, import that, then remove it (avoids docker stdin quirks).
     */
    public function dbImport(string $path, string $dumpFile): string
    {
        $escapedPath = $this->escapePath($path);
        $dumpArg = escapeshellarg($dumpFile);
        $lando = $this->getLandoPath();

        return "cd {$escapedPath} && gzip -dc {$dumpArg} > dumpfile.sql && {$lando} db-import dumpfile.sql && rm -f dumpfile.sql";
    }

    public function composerCreateProject(string $path, string $package, string $name): string
    {
        $escapedPath = $this->escapePath($path);
        $lando = $this->getLandoPath();

        // Use 'lando composer' tooling (defined in .lando.yml) so Composer runs inside the
        // appserver container with the correct PHP version and environment — not via lando ssh.
        // --working-dir tells Composer to treat the themes directory as CWD; $name is the
        // target sub-directory that create-project will create inside it.
        return "cd {$escapedPath} && {$lando} composer create-project {$package} {$name} --working-dir=/app/wp/wp-content/themes";
    }

    public function composerInstall(string $path, string $subdir): string
    {
        $escapedPath = $this->escapePath($path);
        $lando = $this->getLandoPath();

        // Use 'lando composer' tooling instead of 'lando ssh -c "composer install"'.
        // --working-dir points Composer at the theme directory where composer.json lives.
        return "cd {$escapedPath} && {$lando} composer install --working-dir=/app/{$subdir}";
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

        if ($this->platform->isWindows()) {
            // PowerShell 5.1 (Windows 10 default) does not support &&.
            // Use Set-Location + semicolon, and call the exe with & to handle spaces in path.
            $lando = $this->getLandoPath();
            $psLando = '& "'.str_replace('"', '""', $lando).'"';

            return "Set-Location {$escapedPath}; {$psLando} {$landoCmd}";
        }

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

    /**
     * Wrap a long-running silent command with a background heartbeat (Unix only).
     *
     * Several lando commands (wp search-replace, heavy composer/yarn installs) run silently
     * for minutes. WithCommandExecution marks a step complete after 30s of log inactivity;
     * the heartbeat keeps the log alive so the step is not advanced prematurely.
     *
     * Uses the same bash pattern as SshService::buildMysqldumpCommand:
     *   - ';' (not '&&') before the while loop so there is no operator-precedence race
     *   - no extra '( )' around the while loop so $! is the direct shell PID (kill works reliably)
     */
    /**
     * Replace the remote WordPress URL with the local one, and optionally fix theme
     * folder name references if the production theme name differs from the local one.
     *
     * Reads the actual siteurl and template from the imported database rather than
     * trusting remote_sites values — those can diverge (www. prefix, theme slug hyphenation,
     * etc.) which would cause 0 replacements or a blank-page theme mismatch.
     *
     * If the production `template` option differs from $localThemeName, a second
     * search-replace runs to rewrite every serialized path (options, post meta, etc.)
     * that still contains the old theme slug.
     */
    public function wpSearchReplaceImported(string $path, string $localUrl, ?string $localThemeName = null): string
    {
        $lando = $this->getLandoPath();
        $escapedPath = $this->escapePath($path);
        $toArg = escapeshellarg($localUrl);

        $cmd = "cd {$escapedPath}"
            ." && REMOTE_URL=\$({$lando} wp option get siteurl --path=wp 2>/dev/null | tail -1 | tr -d '\\r\\n')"
            ." && echo \"Replacing: \$REMOTE_URL -> {$localUrl}\""
            ." && {$lando} wp search-replace \"\$REMOTE_URL\" {$toArg} --all-tables --path=wp";

        if ($localThemeName) {
            $localThemeArg = escapeshellarg($localThemeName);
            $cmd .= " && PROD_THEME=\$({$lando} wp option get template --path=wp 2>/dev/null | tail -1 | tr -d '\\r\\n')"
                ." && if [ \"\$PROD_THEME\" != {$localThemeArg} ]; then"
                ."   echo \"Theme slug mismatch: \$PROD_THEME -> {$localThemeName}\";"
                ."   {$lando} wp search-replace \"\$PROD_THEME\" {$localThemeArg} --all-tables --path=wp;"
                .' else'
                .'   echo "Theme slug matches: $PROD_THEME";'
                .' fi';
        }

        return $this->platform->isWindows()
            ? $cmd
            : $this->withHeartbeat('[Lando Studio] Domain replacement in progress', $cmd);
    }

    private function withHeartbeat(string $message, string $command): string
    {
        $msg = escapeshellarg($message);
        $trapCleanup = escapeshellarg('kill $LANDODEV_HB 2>/dev/null; wait $LANDODEV_HB 2>/dev/null');

        return 'while sleep 20; do echo '.$msg.'; done & LANDODEV_HB=$!'
            .' && trap '.$trapCleanup.' EXIT'
            .' && ('.$command.')'
            .' && kill $LANDODEV_HB 2>/dev/null && wait $LANDODEV_HB 2>/dev/null && trap - EXIT';
    }
}
