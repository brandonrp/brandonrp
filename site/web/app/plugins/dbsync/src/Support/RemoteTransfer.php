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
        $cmd = 'rsync -az --partial --info=progress2 -e ssh '
            . escapeshellarg($remoteSpec) . ' ' . escapeshellarg($localPath);

        return self::run_rsync_streaming($cmd, $dryRun);
    }

    public static function rsync_push(string $ssh, string $localPath, string $remotePath, bool $dryRun): int
    {
        $host = self::ssh_host($ssh);
        $remoteSpec = $host . ':' . $remotePath;
        $cmd = 'rsync -az --partial --info=progress2 -e ssh '
            . escapeshellarg($localPath) . ' ' . escapeshellarg($remoteSpec);

        return self::run_rsync_streaming($cmd, $dryRun);
    }

    /**
     * Run rsync with streamed output so --info=progress2 can be parsed for [dbsync] rsync_percent lines (admin UI).
     */
    private static function run_rsync_streaming(string $cmd, bool $dryRun): int
    {
        WP_CLI::log('[transfer] ' . $cmd);
        if ($dryRun) {
            return 0;
        }

        $descriptorspec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        // Merge stderr into stdout for --info=progress2 (single stream for parsing).
        $process = @\proc_open($cmd . ' 2>&1', $descriptorspec, $pipes, null, null);
        if (!\is_resource($process)) {
            $exitCode = 0;
            @\exec($cmd . ' 2>&1', $out, $exitCode);

            return (int) $exitCode;
        }

        if (isset($pipes[0]) && \is_resource($pipes[0])) {
            \fclose($pipes[0]);
        }

        $lastPercent = -1;
        $buf = '';
        $stdout = $pipes[1] ?? null;
        if (\is_resource($stdout)) {
            while (!\feof($stdout)) {
                $chunk = \fread($stdout, 8192);
                if ($chunk === false || $chunk === '') {
                    break;
                }
                $buf .= $chunk;
                if (\strlen($buf) > 4000) {
                    $buf = \substr($buf, -4000);
                }
                if (\preg_match_all('/(\d{1,3})%/', $buf, $m)) {
                    $p = (int) \end($m[1]);
                    if ($p > 100) {
                        $p = 100;
                    }
                    if ($p !== $lastPercent) {
                        $lastPercent = $p;
                        WP_CLI::log('[dbsync] rsync_percent: ' . $p);
                    }
                }
            }
            \fclose($stdout);
        }

        if (isset($pipes[2]) && \is_resource($pipes[2])) {
            \fclose($pipes[2]);
        }

        return (int) \proc_close($process);
    }
}

