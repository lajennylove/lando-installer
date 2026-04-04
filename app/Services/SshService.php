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
        $remotePath = "applications/{$remote->db_name}/public_html/wp-content/plugins/";

        return "sshpass -p '{$remote->ssh_password}' rsync -avz -e 'ssh -o StrictHostKeyChecking=no' {$remote->ssh_user}@{$remote->ssh_server_ip}:{$remotePath} {$localPluginsPath}";
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
