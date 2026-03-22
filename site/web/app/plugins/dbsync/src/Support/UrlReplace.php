<?php

namespace BrandonRP\DBSync\Support;

use BrandonRP\DBSync\Util\WpCli;
use WP_CLI;

final class UrlReplace
{
    public static function get_wp_option(string $option, string $env, string $ssh): string
    {
        if ($env === 'prod') {
            if ($ssh === '') {
                WP_CLI::error('Missing `--remote-ssh` required for prod operations.');
                return '';
            }
            // Run remote wp via direct SSH so we capture stderr when it fails (runcommand can exit without surfacing it).
            $host = RemoteTransfer::ssh_host($ssh);
            $path = RemoteTransfer::ssh_remote_path($ssh);
            $allowRoot = self::remote_user_is_root($ssh) ? ' --allow-root' : '';
            $remoteCmd = 'cd ' . escapeshellarg($path) . ' && wp option get ' . escapeshellarg($option) . $allowRoot;
            $cmd = 'ssh -o BatchMode=yes -o StrictHostKeyChecking=no -o ConnectTimeout=10 '
                . escapeshellarg($host) . ' ' . escapeshellarg($remoteCmd) . ' 2>&1';
            $output = [];
            $exitCode = 0;
            @exec($cmd, $output, $exitCode);
            $out = trim(implode("\n", $output));
            if ($exitCode !== 0) {
                WP_CLI::error('Remote wp option get ' . $option . ' failed (exit ' . $exitCode . '): ' . ($out !== '' ? $out : 'no output'));
            }
            return $out;
        }

        return WpCli::run('option get ' . $option, []);
    }

    public static function build_search_replace_cmd(
        string $from,
        string $to,
        bool $dryRun
    ): string {
        $cmd = 'search-replace ' . escapeshellarg($from) . ' ' . escapeshellarg($to);
        $cmd .= ' --all-tables --precise';
        if ($dryRun) {
            $cmd .= ' --dry-run';
        }
        return $cmd;
    }

    public static function run_search_replace_on_env(
        string $env,
        string $ssh,
        string $from,
        string $to,
        bool $dryRun
    ): void {
        if ($from === '' || $to === '' || $from === $to) {
            return;
        }

        if ($env === 'prod') {
            $allowRootArg = self::remote_user_is_root($ssh) ? ' --allow-root' : '';
            WP_CLI::log('[url-replace] remote: --ssh=' . $ssh);
            WpCli::run('--ssh=' . $ssh . $allowRootArg . ' ' . self::build_search_replace_cmd($from, $to, $dryRun), []);
            return;
        }

        WpCli::run(self::build_search_replace_cmd($from, $to, $dryRun), []);
    }

    private static function remote_user_is_root(string $ssh): bool
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

