<?php

namespace App\Services;

use App\Models\RemoteSite;

class SshService
{
    public function buildMysqldumpCommand(RemoteSite $remote, string $localDumpPath): string
    {
        $sshCmd = "sshpass -p '{$remote->ssh_password}' ssh -o StrictHostKeyChecking=no {$remote->ssh_user}@{$remote->ssh_server_ip}";
        $mysqlDump = "mysqldump -u {$remote->db_user} -p'{$remote->db_password}' {$remote->db_name}";

        return "{$sshCmd} \"{$mysqlDump}\" | gzip > {$localDumpPath}";
    }

    public function buildRsyncPluginsCommand(RemoteSite $remote, string $localPluginsPath): string
    {
        $remotePlugins = $this->remotePluginsDirectory($remote);
        $host = "{$remote->ssh_user}@{$remote->ssh_server_ip}";
        $remoteArg = escapeshellarg($remotePlugins);
        $localArg = escapeshellarg($localPluginsPath);

        return "sshpass -p '{$remote->ssh_password}' rsync -avz -e 'ssh -o StrictHostKeyChecking=no' {$host}:{$remoteArg} {$localArg}";
    }

    /**
     * Path to wp-content/plugins on the remote host (rsync source).
     * Prefers explicit {@see RemoteSite::$remote_path}; otherwise legacy pattern relative to SSH home.
     */
    public function remotePluginsDirectory(RemoteSite $remote): string
    {
        $root = $remote->remote_path;
        if (is_string($root) && $root !== '') {
            return rtrim($root, '/').'/wp-content/plugins/';
        }

        return "applications/{$remote->db_name}/public_html/wp-content/plugins/";
    }

    public function buildHtaccessRewriteContent(string $remoteDomain): string
    {
        $remoteDomain = rtrim($remoteDomain, '/');

        return <<<HTACCESS
<IfModule mod_rewrite.c>
RewriteEngine On

# Use local images if they exist, otherwise use the live site
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^wp-content/uploads/(.*)\$ {$remoteDomain}/wp-content/uploads/\$1 [R=301,L]

</IfModule>
HTACCESS;
    }
}
