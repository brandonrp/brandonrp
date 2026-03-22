<?php

namespace BrandonRP\DBSync\Support;

use WP_CLI;

/**
 * Run `wp` on a remote host via SSH (same idea as UrlReplace for prod).
 * Prefer this over WP_CLI::runcommand(..., --ssh=...) for long or critical commands so stderr is captured.
 */
final class RemoteWp
{
    public static function run(string $ssh, string $wpArgs): string
    {
        $host = RemoteTransfer::ssh_host($ssh);
        $path = RemoteTransfer::ssh_remote_path($ssh);
        $wp = self::is_root($ssh) ? 'wp --allow-root ' : 'wp ';
        $remoteCmd = 'cd ' . escapeshellarg($path) . ' && ' . $wp . $wpArgs;
        $cmd = 'ssh -o BatchMode=yes -o StrictHostKeyChecking=no -o ConnectTimeout=60 '
            . '-o ServerAliveInterval=30 -o ServerAliveCountMax=120 '
            . escapeshellarg($host) . ' ' . escapeshellarg($remoteCmd) . ' 2>&1';
        $output = [];
        $exitCode = 0;
        @exec($cmd, $output, $exitCode);
        $out = trim(implode("\n", $output));
        if ($exitCode !== 0) {
            WP_CLI::error('Remote wp failed (exit ' . $exitCode . '): ' . ($out !== '' ? $out : 'no output'));
        }

        return $out;
    }

    private static function is_root(string $ssh): bool
    {
        $ssh = trim($ssh);
        $tildePos = strpos($ssh, '~');
        if ($tildePos !== false) {
            $ssh = substr($ssh, 0, $tildePos);
        }
        $slashPos = strpos($ssh, '/');
        if ($slashPos !== false) {
            $ssh = substr($ssh, 0, $slashPos);
        }
        $atPos = strpos($ssh, '@');
        if ($atPos === false) {
            return false;
        }
        $user = strtolower(substr($ssh, 0, $atPos));

        return $user === 'root';
    }
}
