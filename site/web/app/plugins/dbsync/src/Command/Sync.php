<?php

namespace BrandonRP\DBSync\Command;

use BrandonRP\DBSync\Support\RemoteTransfer;
use BrandonRP\DBSync\Support\RemoteWp;
use BrandonRP\DBSync\Support\UrlReplace;
use BrandonRP\DBSync\Util\WpCli;
use WP_CLI;

final class Sync extends BaseCommand
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

        $compress = !empty($assoc_args['compress']);
        $excludeTablesCsv = isset($assoc_args['exclude-tables']) ? (string) $assoc_args['exclude-tables'] : '';

        if ($compress && ($from === 'prod' || $to === 'prod')) {
            // Built-in WP-CLI `wp db export|import` does not provide a gzip-compatible option chain
            // in a way we can reliably support without requiring our plugin on the remote.
            WP_CLI::warning('`--compress` is ignored when prod is involved (remote dump stays uncompressed).');
            $compress = false;
        }

        $stamp = gmdate('Ymd-His') . '-' . bin2hex(random_bytes(3));
        $sourceDump = '/tmp/dbsync-' . $stamp . '.sql';
        $localDump = '/tmp/dbsync-' . $stamp . '.sql';
        $dumpInput = $sourceDump;
        $localDumpInput = $localDump;

        // Replacement options: infer from env home/siteurl values.
        WP_CLI::log('Fetching home/siteurl for source and destination...');
        try {
            WP_CLI::log('  option home from ' . $from . '...');
            $fromHome = UrlReplace::get_wp_option('home', $from, $ssh);
            WP_CLI::log('  option siteurl from ' . $from . '...');
            $fromSiteUrl = UrlReplace::get_wp_option('siteurl', $from, $ssh);
            WP_CLI::log('  option home from ' . $to . '...');
            $toHome = UrlReplace::get_wp_option('home', $to, $ssh);
            WP_CLI::log('  option siteurl from ' . $to . '...');
            $toSiteUrl = UrlReplace::get_wp_option('siteurl', $to, $ssh);
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            WP_CLI::log('Exception: ' . get_class($e) . ' — ' . ($msg !== '' ? $msg : '(no message)'));
            WP_CLI::error('Failed to fetch options.');
        }

        // Discover all tables from the source environment so export can include custom tables.
        $remoteAllowRoot = $this->remote_user_is_root($ssh) ? ' --allow-root' : '';

        $tablesCsv = $from === 'prod'
            ? WpCli::run('--ssh=' . $ssh . $remoteAllowRoot . ' db tables --all-tables --format=csv', [])
            : WpCli::run('db tables --all-tables --format=csv', []);
        $tables = $tablesCsv === '' ? [] : preg_split('/\s*,\s*/', trim($tablesCsv));
        if (!is_array($tables) || $tables === [] || count($tables) === 0) {
            WP_CLI::error('No tables discovered for export (check DB connectivity and privileges).');
            return;
        }
        $tablesArg = implode(',', array_values(array_filter(array_map(
            static fn ($t) => trim((string) $t, " \t\n\r\0\x0B\"'"),
            $tables
        ), static fn ($t) => $t !== '')));

        // $remoteAllowRoot already computed above.

        $exportCmd = '';
        if ($from === 'prod') {
            $exportCmd = '--ssh=' . $ssh . $remoteAllowRoot
                . ' db export ' . escapeshellarg($sourceDump)
                . ' --add-drop-table --defaults'
                . ' --tables=' . $tablesArg;
            if ($excludeTablesCsv !== '') {
                $exportCmd .= ' --exclude_tables=' . escapeshellarg($excludeTablesCsv);
            }
        } else {
            $exportCmd = 'db export ' . escapeshellarg($localDump)
                . ' --add-drop-table --defaults'
                . ' --tables=' . $tablesArg;
            if ($excludeTablesCsv !== '') {
                $exportCmd .= ' --exclude_tables=' . escapeshellarg($excludeTablesCsv);
            }
        }

        $importCmd = '';
        if ($to === 'local') {
            $importCmd = 'db import ' . escapeshellarg($localDumpInput);
        } else {
            $importCmd = '--ssh=' . $ssh . $remoteAllowRoot
                . ' db import ' . escapeshellarg($dumpInput);
        }

        $searchReplaceParts = [];
        $searchReplaceParts[] = UrlReplace::build_search_replace_cmd($fromHome, $toHome, false);
        if ($fromSiteUrl !== $fromHome || $toSiteUrl !== $toHome) {
            $searchReplaceParts[] = UrlReplace::build_search_replace_cmd($fromSiteUrl, $toSiteUrl, false);
        }

        WP_CLI::line('DB sync planning: ' . strtoupper($from) . ' -> ' . strtoupper($to));

        if ($dry_run) {
            WP_CLI::line('Planned actions (dry-run):');
            WP_CLI::line('- Export (on ' . $from . '): ' . $exportCmd);
            if ($from !== $to) {
                if ($from === 'prod' && $to === 'local') {
                    WP_CLI::line('- Transfer prod dump to local: ' . $sourceDump . ' -> ' . $localDumpInput);
                } elseif ($from === 'local' && $to === 'prod') {
                    WP_CLI::line('- Transfer local dump to prod: ' . $localDumpInput . ' -> ' . $dumpInput);
                }
            }
            WP_CLI::line('- Import (on ' . $to . '): ' . $importCmd);
            WP_CLI::line('- URL replacement commands (on ' . $to . '):');
            foreach ($searchReplaceParts as $sr) {
                WP_CLI::line('  - ' . ($to === 'prod' ? '(remote via SSH) wp ' : 'wp ') . $sr);
            }
            WP_CLI::warning('Add `--run` to execute export/import/transfer/replace for real.');
            return;
        }

        $localRowsBeforeExport = '';
        if ($from === 'local' && $to === 'prod') {
            $localRowsBeforeExport = trim(WpCli::run(
                'eval ' . escapeshellarg('global $wpdb; echo (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts}");'),
                []
            ));
            WP_CLI::log('Local wp_posts row count before export: ' . $localRowsBeforeExport);
        }

        // 1) Export
        WP_CLI::log('Exporting from source...');
        WpCli::run($exportCmd, []);

        // Ensure local file paths.
        if ($from === 'prod' && $to === 'local') {
            WP_CLI::log('Transferring prod dump to local...');
            $rsyncExit = RemoteTransfer::rsync_pull($ssh, $sourceDump, $localDumpInput, false);
            if ($rsyncExit !== 0) {
                WP_CLI::error('rsync pull failed with exit code ' . $rsyncExit);
            }
        } elseif ($from === 'local' && $to === 'prod') {
            WP_CLI::log('Transferring local dump to prod...');
            $rsyncExit = RemoteTransfer::rsync_push($ssh, $localDumpInput, $dumpInput, false);
            if ($rsyncExit !== 0) {
                WP_CLI::error('rsync push failed with exit code ' . $rsyncExit);
            }
        }

        // 2) Import on destination (remote: use SSH shell so stderr/exit codes are reliable)
        if ($to === 'local') {
            WP_CLI::log('Importing into local...');
            WpCli::run($importCmd, []);
        } else {
            WP_CLI::log('Importing into prod (remote via SSH)...');
            RemoteWp::run($ssh, 'db import ' . escapeshellarg($dumpInput));
        }

        if ($from === 'local' && $to === 'prod' && $localRowsBeforeExport !== '') {
            $remoteRows = trim(RemoteWp::run(
                $ssh,
                'eval ' . escapeshellarg('global $wpdb; echo (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts}");')
            ));
            WP_CLI::log('Remote wp_posts row count after import: ' . $remoteRows . ' (expected ~' . $localRowsBeforeExport . ' from local)');
            if ($remoteRows !== $localRowsBeforeExport) {
                WP_CLI::warning(
                    'Post count mismatch after import. Check production MySQL error log, max_allowed_packet, disk space, '
                    . 'and that this SSH path matches the live site database.'
                );
            }
        }

        // 3) URL replacement on destination
        WP_CLI::log('Updating URLs (serialized-safe)...');
        if ($to === 'prod') {
            foreach ($searchReplaceParts as $sr) {
                RemoteWp::run($ssh, $sr);
            }
        } else {
            foreach ($searchReplaceParts as $sr) {
                WpCli::run($sr, []);
            }
        }

        // Clear any object cache so the updated values are used.
        if ($to === 'prod') {
            RemoteWp::run($ssh, 'cache flush');
        } else {
            WpCli::run('cache flush', []);
        }

        WP_CLI::success('DB sync complete.');
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

        if (strpos($ssh, '@') === false) {
            return false;
        }
        $user = strtolower(substr($ssh, 0, strpos($ssh, '@')));
        return $user === 'root';
    }
}

