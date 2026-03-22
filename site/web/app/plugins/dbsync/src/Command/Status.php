<?php

namespace BrandonRP\DBSync\Command;

use BrandonRP\DBSync\Util\WpCli;
use WP_CLI;

final class Status extends BaseCommand
{
    public function run($args, array $assoc_args): void
    {
        $ssh = (string) ($assoc_args['remote-ssh'] ?? '');
        $env = strtolower((string) ($assoc_args['env'] ?? 'local'));

        if (!in_array($env, ['prod', 'local'], true)) {
            WP_CLI::error('`--env` must be one of: prod, local');
            return;
        }

        $prefixCmd = 'db prefix';
        $tableCmd = 'db tables --all-tables --format=csv';

        if ($env === 'prod') {
            if ($ssh === '') {
                WP_CLI::error('Missing `--remote-ssh` for prod status.');
                return;
            }
            $allowRootArg = $this->remote_user_is_root($ssh) ? ' --allow-root' : '';
            $prefixCmd = '--ssh=' . $ssh . $allowRootArg . ' ' . $prefixCmd;
            $tableCmd = '--ssh=' . $ssh . $allowRootArg . ' ' . $tableCmd;
        }

        $prefix = WpCli::run($prefixCmd, []);
        $csv = WpCli::run($tableCmd, []);
        $tables = $csv === '' ? [] : preg_split('/\s*,\s*/', trim($csv));

        $uploadDirCmd = 'eval ' . escapeshellarg('echo wp_upload_dir()["basedir"];');
        if ($env === 'prod') {
            $allowRootArg = $this->remote_user_is_root($ssh) ? ' --allow-root' : '';
            $uploadDirCmd = '--ssh=' . $ssh . $allowRootArg . ' ' . $uploadDirCmd;
        }
        $uploads = WpCli::run($uploadDirCmd, []);

        WP_CLI::line('DBSync status for ' . strtoupper($env));
        WP_CLI::line('- table_prefix: ' . $prefix);
        WP_CLI::line('- tables (count): ' . count($tables));
        WP_CLI::line('- uploads basedir: ' . $uploads);
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

