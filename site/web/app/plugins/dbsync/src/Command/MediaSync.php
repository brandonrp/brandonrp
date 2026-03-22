<?php

namespace BrandonRP\DBSync\Command;

use BrandonRP\DBSync\Support\RemoteTransfer;
use BrandonRP\DBSync\Util\WpCli;
use WP_CLI;

final class MediaSync extends BaseCommand
{
    public function run($args, array $assoc_args): void
    {
        $dry_run = $this->is_dry_run($assoc_args);

        $from = strtolower((string) ($assoc_args['from'] ?? 'prod'));
        $to = strtolower((string) ($assoc_args['to'] ?? 'local'));

        if (!in_array($from, ['prod', 'local'], true) || !in_array($to, ['prod', 'local'], true)) {
            WP_CLI::error('`--from` and `--to` must be one of: prod, local');
            return;
        }

        $ssh = (string) ($assoc_args['remote-ssh'] ?? '');
        if (($from === 'prod' || $to === 'prod') && $ssh === '') {
            WP_CLI::error('Missing `--remote-ssh` required when syncing to/from prod.');
            return;
        }

        $mode = strtolower((string) ($assoc_args['mode'] ?? 'push-only'));
        if ($mode !== 'push-only') {
            WP_CLI::warning('Only `--mode=push-only` is supported in this version; continuing with push-only.');
        }

        // uploads-only scope.
        $fromUploads = $this->get_uploads_basedir($from, $ssh);
        $toUploads = $this->get_uploads_basedir($to, $ssh);

        if ($dry_run) {
            WP_CLI::line('Planned media sync (dry-run): ' . strtoupper($from) . ' -> ' . strtoupper($to));
            WP_CLI::line('- uploads basedir (from): ' . $fromUploads);
            WP_CLI::line('- uploads basedir (to):   ' . $toUploads);
            WP_CLI::warning('Add `--run` to execute rsync.');
            return;
        }

        if ($from === $to) {
            WP_CLI::warning('Nothing to do: `--from` and `--to` are the same.');
            return;
        }

        // RemoteTransfer defaults to rsync without `--delete`, which matches push-only behavior.

        $sourceHasTrailing = rtrim($fromUploads, '/');
        $destHasTrailing = rtrim($toUploads, '/');

        if ($from === 'local' && $to === 'prod') {
            RemoteTransfer::rsync_push($ssh, $sourceHasTrailing . '/', $destHasTrailing . '/', false);
        } elseif ($from === 'prod' && $to === 'local') {
            RemoteTransfer::rsync_pull($ssh, $sourceHasTrailing . '/', $destHasTrailing . '/', false);
        }

        WP_CLI::success('Media sync complete.');
    }

    private function get_uploads_basedir(string $env, string $ssh): string
    {
        $phpExpr = 'echo wp_upload_dir()["basedir"];';
        if ($env === 'prod') {
            // Run remote wp via direct SSH so we capture stderr when it fails.
            $host = RemoteTransfer::ssh_host($ssh);
            $path = RemoteTransfer::ssh_remote_path($ssh);
            $allowRoot = $this->remote_user_is_root($ssh) ? ' --allow-root' : '';
            $remoteCmd = 'cd ' . escapeshellarg($path) . ' && wp eval ' . escapeshellarg($phpExpr) . $allowRoot;
            $cmd = 'ssh -o BatchMode=yes -o StrictHostKeyChecking=no -o ConnectTimeout=10 '
                . escapeshellarg($host) . ' ' . escapeshellarg($remoteCmd) . ' 2>&1';
            $output = [];
            $exitCode = 0;
            @exec($cmd, $output, $exitCode);
            $out = trim(implode("\n", $output));
            if ($exitCode !== 0) {
                WP_CLI::error('Remote wp eval (uploads basedir) failed (exit ' . $exitCode . '): ' . ($out !== '' ? $out : 'no output'));
            }
            return $out;
        }

        return WpCli::run('eval ' . escapeshellarg($phpExpr), []);
    }

    private function remote_user_is_root(string $ssh): bool
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

