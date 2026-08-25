<?php

declare(strict_types=1);

namespace Core;

/**
 * Storage Auto-Healing & Permissions Enforcer for Shared Hosting / cPanel.
 */
class Storage
{
    /**
     * Ensure all essential storage subdirectories exist with correct permissions and security files.
     *
     * @param string|null $rootDir
     */
    public static function ensureDirectories(?string $rootDir = null): void
    {
        $base = rtrim($rootDir ?? dirname(__DIR__), '/\\');
        $storageDir = "{$base}/storage";

        $subdirs = [
            "{$storageDir}/cache",
            "{$storageDir}/logs",
            "{$storageDir}/uploads",
            "{$storageDir}/backups",
        ];

        foreach ($subdirs as $dir) {
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }

            $gitkeep = "{$dir}/.gitkeep";
            if (!file_exists($gitkeep)) {
                @file_put_contents($gitkeep, "# Preserve directory structure\n");
            }
        }

        // Enforce script execution denial in uploads directory
        $uploadsHtaccess = "{$storageDir}/uploads/.htaccess";
        if (!file_exists($uploadsHtaccess)) {
            $htaccessContent = <<<HTACCESS
# Block PHP/CGI script execution in uploads directory
<FilesMatch "\.(php|phtml|php3|php4|php5|php7|php8|phar|pl|py|cgi|sh|bash|exe)$">
    Order Deny,Allow
    Deny from all
</FilesMatch>

Options -ExecCGI -Indexes
HTACCESS;
            @file_put_contents($uploadsHtaccess, $htaccessContent);
        }
    }
}
