<?php

namespace BrandonRP\DBSync\Command;

use BrandonRP\DBSync\Util\WpCli;
use WP_CLI;

final class Export extends BaseCommand
{
    public function run($args, array $assoc_args): void
    {
        // Your initial scope selection was `all_tables`, so default to including custom tables.
        // You can omit this flag in the future if you want "core prefix only" behavior.
        if (!isset($assoc_args['include-custom-tables'])) {
            $assoc_args['include-custom-tables'] = true;
        }

        $output = (string) ($assoc_args['output'] ?? '');
        $dry_run = $this->is_dry_run($assoc_args);

        // We need a table prefix to decide what to dump; pull it from $wpdb via WP-CLI.
        $prefix = WpCli::run('db prefix', []);
        $prefix = $prefix !== '' ? $prefix : (string) ($assoc_args['prefix'] ?? 'wp_');

        $tables = null;
        if (isset($assoc_args['tables'])) {
            $tables = DbSyncFlow::parse_csv_values((string) $assoc_args['tables']);
        } else {
            // Default to "all_tables" scope per your request: use an explicit table list.
            // You can still exclude specific tables via `--exclude-tables`.
            $tables = DbSyncFlow::get_all_tables_for_prefix_and_custom($prefix, $assoc_args);
        }

        if ($output === '') {
            $output = '/tmp/dbsync-' . gmdate('Ymd-His') . '.sql';
        }

        $compress = !empty($assoc_args['compress']);
        $finalOutput = $output;
        if ($compress) {
            $finalOutput .= '.gz';
        }

        $tablesArg = $tables !== null ? '--tables=' . implode(',', $tables) : '';
        $excludeArg = isset($assoc_args['exclude-tables'])
            ? '--exclude_tables=' . implode(',', DbSyncFlow::parse_csv_values((string) $assoc_args['exclude-tables']))
            : '';

        // wp db export syntax: `wp db export [<file>] [--tables=...] [--exclude_tables=...]`
        $outputArg = preg_match('/\s/', $output) ? escapeshellarg($output) : $output;
        $cmd = 'db export ' . $tablesArg . ' ' . $excludeArg . ' --add-drop-table --defaults ' . $outputArg;

        if ($dry_run) {
            WP_CLI::line('Planned export (dry-run):');
            WP_CLI::line('- tables: ' . count($tables) . ' tables');
            WP_CLI::line('- output: ' . $finalOutput);
            WP_CLI::line('- command: ' . $cmd);
            if ($compress) {
                WP_CLI::line('- command: gzip -f ' . $output);
            }
            return;
        }

        WP_CLI::line('Exporting WordPress database...');
        // Execute export.
        WpCli::run($cmd, []);

        // Optional compression.
        if ($compress) {
            $gzipCmd = 'gzip -f ' . escapeshellarg($output);
            $exitCode = 0;
            @exec($gzipCmd, $outLines, $exitCode);
            if ($exitCode !== 0) {
                WP_CLI::error('gzip failed (exit code ' . $exitCode . '); aborting compressed export.');
            }
        }

        WP_CLI::success('Export complete: ' . $finalOutput);
    }
}

