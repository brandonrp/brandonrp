<?php

namespace BrandonRP\DBSync\Command;

use BrandonRP\DBSync\Support\UrlReplace;
use BrandonRP\DBSync\Util\WpCli;
use WP_CLI;

final class Import extends BaseCommand
{
    public function run($args, array $assoc_args): void
    {
        $input = (string) ($assoc_args['input'] ?? '');
        if ($input === '' && isset($args[0])) {
            $input = (string) $args[0];
        }

        if ($input === '') {
            WP_CLI::error('Missing `--input` (or pass input file as the first argument).');
            return;
        }

        $dry_run = $this->is_dry_run($assoc_args);
        $before_backup = !empty($assoc_args['before-backup']);

        $tmpSql = '';
        $isGz = str_ends_with($input, '.gz');
        if ($isGz) {
            // Decompress to a temp SQL file for wp db import.
            $tmpSql = '/tmp/dbsync-import-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.sql';
        }

        if ($dry_run) {
            WP_CLI::line('Planned import (dry-run):');
            WP_CLI::line('- input: ' . $input);
            if ($isGz) {
                WP_CLI::line('- command: gunzip -> ' . $tmpSql);
            }
            if ($before_backup) {
                WP_CLI::line('- command: wp db export backup before import');
            }
            WP_CLI::line('- command: wp db import ' . ($isGz ? $tmpSql : $input));
            $this->log_planned_url_replace($assoc_args);
            return;
        }

        if (!is_readable($input) && $isGz === false) {
            WP_CLI::warning('Input file is not readable (continuing anyway): ' . $input);
        }

        // Optional backup.
        if ($before_backup) {
            $backupPath = '/tmp/dbsync-backup-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.sql';
            WP_CLI::log('Creating destination backup: ' . $backupPath);
            // Export all tables by default (same scope as export).
            $backupArg = preg_match('/\s/', $backupPath) ? escapeshellarg($backupPath) : $backupPath;
            WpCli::run('db export ' . $backupArg . ' --add-drop-table --defaults', []);
        }

        if ($isGz) {
            WP_CLI::log('Decompressing: ' . $input);
            $this->gunzip_to_file($input, $tmpSql);
        }

        $sqlToImport = $isGz ? $tmpSql : $input;
        WP_CLI::log('Importing dump into current WordPress...');
        $sqlArg = preg_match('/\s/', $sqlToImport) ? escapeshellarg($sqlToImport) : $sqlToImport;
        WpCli::run('db import ' . $sqlArg, []);

        if ($isGz && $tmpSql !== '' && file_exists($tmpSql)) {
            @unlink($tmpSql);
        }

        $this->run_url_replace_after_import($assoc_args);

        WP_CLI::success('Import complete.');
    }

    /**
     * @return array<int, array{0: string, 1: string}>
     */
    private function url_replace_pairs(array $assoc_args): array
    {
        $homeFrom = self::assoc_string($assoc_args, 'replace-url-home-from');
        $homeTo = self::assoc_string($assoc_args, 'replace-url-home-to');
        $siteFrom = self::assoc_string($assoc_args, 'replace-url-siteurl-from');
        $siteTo = self::assoc_string($assoc_args, 'replace-url-siteurl-to');

        $pairs = [];
        if ($homeFrom !== '' && $homeTo !== '' && $homeFrom !== $homeTo) {
            $pairs[] = [$homeFrom, $homeTo];
        }
        if ($siteFrom !== '' && $siteTo !== '' && $siteFrom !== $siteTo) {
            $pairs[] = [$siteFrom, $siteTo];
        }

        return $pairs;
    }

    private static function assoc_string(array $assoc_args, string $hyphenKey): string
    {
        $underKey = str_replace('-', '_', $hyphenKey);

        return trim((string) ($assoc_args[$hyphenKey] ?? $assoc_args[$underKey] ?? ''));
    }

    private function log_planned_url_replace(array $assoc_args): void
    {
        $pairs = $this->url_replace_pairs($assoc_args);
        if ($pairs === []) {
            return;
        }
        WP_CLI::line('- URL replacement (after import, same as DB sync):');
        foreach ($pairs as [$from, $to]) {
            WP_CLI::line('  - wp ' . UrlReplace::build_search_replace_cmd($from, $to, false));
        }
        WP_CLI::line('  - wp cache flush');
    }

    private function run_url_replace_after_import(array $assoc_args): void
    {
        $pairs = $this->url_replace_pairs($assoc_args);
        if ($pairs === []) {
            return;
        }

        WP_CLI::log('Replacing URLs (serialized-safe)...');
        foreach ($pairs as [$from, $to]) {
            WpCli::run(UrlReplace::build_search_replace_cmd($from, $to, false), []);
        }
        WpCli::run('cache flush', []);
    }

    /**
     * Decompress a .gz file into a destination file (binary-safe).
     */
    private function gunzip_to_file(string $gzPath, string $destPath): void
    {
        $in = @gzopen($gzPath, 'rb');
        if ($in === false) {
            WP_CLI::error('Failed to open gz file: ' . $gzPath);
            return;
        }

        $out = @fopen($destPath, 'wb');
        if ($out === false) {
            gzclose($in);
            WP_CLI::error('Failed to create temp sql file: ' . $destPath);
            return;
        }

        while (!gzeof($in)) {
            $chunk = gzread($in, 1024 * 1024);
            if ($chunk === false) {
                break;
            }
            fwrite($out, $chunk);
        }

        gzclose($in);
        fclose($out);
    }
}

