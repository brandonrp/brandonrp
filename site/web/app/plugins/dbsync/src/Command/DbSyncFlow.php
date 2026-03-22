<?php

namespace BrandonRP\DBSync\Command;

use BrandonRP\DBSync\Util\WpCli;
use WP_CLI;

final class DbSyncFlow
{
    public static function get_all_tables_for_prefix_and_custom(string $prefix, array $assoc_args): array
    {
        $include_custom = isset($assoc_args['include-custom-tables']);
        $exclude_tables = self::parse_csv_assoc($assoc_args, 'exclude-tables');

        $tables = [];

        // Always include tables matching current $wpdb prefix.
        $tables_prefix = WpCli::run('db tables --all-tables --format=csv', []);
        $all = self::parse_csv_values($tables_prefix);

        if ($include_custom) {
            // Include all tables (custom + WP core).
            $tables = $all;
        } else {
            // Only include WP core tables (and any others that match the prefix).
            foreach ($all as $t) {
                if (strpos($t, $prefix) === 0) {
                    $tables[] = $t;
                }
            }
        }

        if ($exclude_tables !== []) {
            $tables = array_values(array_filter(
                $tables,
                static fn (string $t): bool => !in_array($t, $exclude_tables, true)
            ));
        }

        return $tables;
    }

    public static function parse_csv_assoc(array $assoc_args, string $key): array
    {
        if (!isset($assoc_args[$key])) {
            return [];
        }

        return self::parse_csv_values((string) $assoc_args[$key]);
    }

    public static function parse_csv_values(string $csv): array
    {
        $parts = preg_split('/\s*,\s*/', trim($csv));
        if ($parts === false) {
            return [];
        }

        $parts = array_values(array_filter($parts, static fn ($v) => $v !== ''));
        return $parts;
    }
}

