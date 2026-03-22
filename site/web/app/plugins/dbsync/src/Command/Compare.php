<?php

namespace BrandonRP\DBSync\Command;

use BrandonRP\DBSync\Support\RemoteTransfer;
use BrandonRP\DBSync\Util\WpCli;
use WP_CLI;

/**
 * Compare local vs production DB signals (prefix, row counts, core version) to debug sync issues.
 */
final class Compare extends BaseCommand
{
    public function run($args, array $assoc_args): void
    {
        $ssh = (string) ($assoc_args['remote-ssh'] ?? '');
        if ($ssh === '') {
            WP_CLI::error('Missing `--remote-ssh` (e.g. root@64.23.203.166~/srv/www/your-site/current).');
            return;
        }

        $checkPostType = isset($assoc_args['check-post-type']) ? trim((string) $assoc_args['check-post-type']) : '';

        WP_CLI::line('');
        WP_CLI::line('=== LOCAL (this machine / current wp-cli.yml) ===');

        $prefixLocal = $this->local_wp('config get table_prefix');
        $coreLocal = $this->local_wp('core version');
        $postsLocal = $this->local_count_posts('');
        $pagesLocal = $this->local_count_posts('page');

        WP_CLI::line('table_prefix:     ' . $prefixLocal);
        WP_CLI::line('WordPress version: ' . $coreLocal);
        WP_CLI::line('posts (all types): ' . $postsLocal);
        WP_CLI::line('pages:             ' . $pagesLocal);

        if ($checkPostType !== '') {
            $cptLocal = $this->local_count_posts($checkPostType);
            WP_CLI::line('posts (type ' . $checkPostType . '): ' . $cptLocal);
        }

        WP_CLI::line('');
        WP_CLI::line('=== REMOTE (production via SSH) ===');

        $prefixRemote = $this->remote_wp($ssh, 'config get table_prefix');
        $coreRemote = $this->remote_wp($ssh, 'core version');
        $postsRemote = $this->remote_count_posts($ssh, '');
        $pagesRemote = $this->remote_count_posts($ssh, 'page');

        WP_CLI::line('table_prefix:     ' . $prefixRemote);
        WP_CLI::line('WordPress version: ' . $coreRemote);
        WP_CLI::line('posts (all types): ' . $postsRemote);
        WP_CLI::line('pages:             ' . $pagesRemote);

        if ($checkPostType !== '') {
            $cptRemote = $this->remote_count_posts($ssh, $checkPostType);
            WP_CLI::line('posts (type ' . $checkPostType . '): ' . $cptRemote);
        }

        WP_CLI::line('');
        WP_CLI::line('=== CHECKS ===');

        if ($prefixLocal !== $prefixRemote) {
            WP_CLI::warning(
                'TABLE PREFIX MISMATCH: local="' . $prefixLocal . '" vs remote="' . $prefixRemote . '". '
                . 'Imports use local table names; production must use the same DB_PREFIX or WordPress will read empty tables.'
            );
        } else {
            WP_CLI::log('OK: Table prefix matches.');
        }

        if ($coreLocal !== $coreRemote) {
            WP_CLI::warning(
                'Core version differs: local ' . $coreLocal . ' vs remote ' . $coreRemote . '. '
                . 'Deploy the same composer.lock / web/wp as local, then run database upgrades if prompted.'
            );
        } else {
            WP_CLI::log('OK: WordPress core version matches.');
        }

        if ((int) $postsRemote === 0 && (int) $postsLocal > 0) {
            WP_CLI::warning(
                'Remote has 0 posts but local has ' . $postsLocal . '. '
                . 'If you expected a populated site, re-check prefix, that import finished, and the correct database.'
            );
        }

        WP_CLI::line('');
        WP_CLI::line('Tip: pass --check-post-type=your_cpt_slug to compare a custom post type count.');
    }

    private function local_wp(string $wpArgs): string
    {
        return trim(WpCli::run($wpArgs, []));
    }

    private function count_posts_eval_code(string $postType): string
    {
        if ($postType === '') {
            return 'global $wpdb; echo (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts}");';
        }
        $pt = var_export($postType, true);

        return 'global $wpdb; echo (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s", ' . $pt . '));';
    }

    private function local_count_posts(string $postType): string
    {
        $php = $this->count_posts_eval_code($postType);

        return trim(WpCli::run('eval ' . escapeshellarg($php), []));
    }

    private function remote_count_posts(string $ssh, string $postType): string
    {
        $php = $this->count_posts_eval_code($postType);

        return trim($this->remote_wp($ssh, 'eval ' . escapeshellarg($php)));
    }

    private function remote_wp(string $ssh, string $wpArgs): string
    {
        $host = RemoteTransfer::ssh_host($ssh);
        $path = RemoteTransfer::ssh_remote_path($ssh);
        $wp = $this->remote_user_is_root($ssh) ? 'wp --allow-root ' : 'wp ';
        $remoteCmd = 'cd ' . escapeshellarg($path) . ' && ' . $wp . $wpArgs;
        $cmd = 'ssh -o BatchMode=yes -o StrictHostKeyChecking=no -o ConnectTimeout=10 '
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
