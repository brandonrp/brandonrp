<?php

namespace BrandonRP\DBSync\Support;

use WP_CLI;

final class RemoteTransfer
{
    /**
     * Extract the host portion from a WP-CLI style ssh value.
     *
     * WP-CLI allows: user@host~/some/path. For rsync/scp we only want user@host.
     */
    public static function ssh_host(string $ssh): string
    {
        $ssh = trim($ssh);
        // Strip WP-CLI's extended syntax "...~/path" or ".../path" down to "user@host[:port]".
        $tildePos = strpos($ssh, '~');
        if ($tildePos !== false) {
            $ssh = substr($ssh, 0, $tildePos);
        }
        $slashPos = strpos($ssh, '/');
        if ($slashPos !== false) {
            $ssh = substr($ssh, 0, $slashPos);
        }
        return $ssh;
    }

    /**
     * Extract the remote path from a WP-CLI style ssh value (e.g. user@host~/srv/www/site/current -> /srv/www/site/current).
     */
    public static function ssh_remote_path(string $ssh): string
    {
        $ssh = trim($ssh);
        $tildePos = strpos($ssh, '~');
        if ($tildePos !== false) {
            $path = substr($ssh, $tildePos + 1);
            return $path !== '' ? $path : '/';
        }
        $slashPos = strpos($ssh, '/');
        if ($slashPos !== false) {
            return substr($ssh, $slashPos);
        }
        return '/';
    }

    public static function rsync_pull(string $ssh, string $remotePath, string $localPath, bool $dryRun): int
    {
        $host = self::ssh_host($ssh);
        $remoteSpec = $host . ':' . $remotePath;
        $cmd = 'rsync -az --partial --progress -e ssh ' . escapeshellarg($remoteSpec) . ' ' . escapeshellarg($localPath);
        WP_CLI::log('[transfer] ' . $cmd);
        if ($dryRun) {
            return 0;
        }
        $exitCode = 0;
        @exec($cmd, $out, $exitCode);
        return (int) $exitCode;
    }

    public static function rsync_push(string $ssh, string $localPath, string $remotePath, bool $dryRun): int
    {
        $host = self::ssh_host($ssh);
        $remoteSpec = $host . ':' . $remotePath;
        $cmd = 'rsync -az --partial --progress -e ssh ' . escapeshellarg($localPath) . ' ' . escapeshellarg($remoteSpec);
        WP_CLI::log('[transfer] ' . $cmd);
        if ($dryRun) {
            return 0;
        }
        $exitCode = 0;
        @exec($cmd, $out, $exitCode);
        return (int) $exitCode;
    }
}

