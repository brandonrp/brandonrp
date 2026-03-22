<?php

namespace BrandonRP\DBSync\Admin;

use BrandonRP\DBSync\Admin\AdminPage;

final class AdminHooks
{
    public static function init(): void
    {
        \add_action('admin_menu', [__CLASS__, 'register_menu']);
        \add_action('admin_post_dbsync_action', [__CLASS__, 'handle_action']);
        \add_action('wp_ajax_dbsync_job_status', [__CLASS__, 'ajax_job_status']);
    }

    public static function ajax_job_status(): void
    {
        if (!\current_user_can('manage_options')) {
            \wp_send_json_error(['message' => 'Forbidden'], 403);
        }
        $nonce = isset($_REQUEST['_wpnonce']) ? (string) $_REQUEST['_wpnonce'] : '';
        if (!\wp_verify_nonce($nonce, 'dbsync_job_status')) {
            \wp_send_json_error(['message' => 'Invalid nonce'], 403);
        }
        $jobId = isset($_REQUEST['job_id']) ? (string) $_REQUEST['job_id'] : '';
        if ($jobId === '' || !\preg_match('/^job_[a-f0-9]{12}$/', $jobId)) {
            \wp_send_json_error(['message' => 'Invalid job_id'], 400);
        }
        $status = AdminPage::get_job_status($jobId);
        \wp_send_json_success($status);
    }

    public static function register_menu(): void
    {
        \add_menu_page(
            'DBSync',
            'DB Sync',
            'manage_options',
            'dbsync',
            [AdminPage::class, 'render'],
            'dashicons-database-export',
            80
        );
    }

    public static function handle_action(): void
    {
        if (!\current_user_can('manage_options')) {
            \wp_die('Insufficient permissions.');
        }

        $nonce = (string) ($_POST['_wpnonce'] ?? '');
        if (!$nonce || !\wp_verify_nonce($nonce, 'dbsync_action')) {
            \wp_die('Invalid request (nonce).');
        }

        $actionType = (string) ($_POST['dbsync_action_type'] ?? 'dbsync');
        $runMode = (string) ($_POST['dbsync_run_mode'] ?? 'preview'); // preview|run

        $from = strtolower((string) ($_POST['from'] ?? 'prod'));
        $to = strtolower((string) ($_POST['to'] ?? 'local'));

        $ssh = (string) ($_POST['remote_ssh'] ?? ($_POST['remote_ssh_media'] ?? ''));
        $compress = !empty($_POST['compress']);

        $excludeTables = (string) ($_POST['exclude_tables'] ?? '');
        $excludeTables = trim($excludeTables);
        $excludeTables = $excludeTables === '' ? '' : $excludeTables;

        if (!in_array($from, ['prod', 'local'], true) || !in_array($to, ['prod', 'local'], true)) {
            \wp_die('Invalid from/to values.');
        }

        if (($from === 'prod' || $to === 'prod') && $ssh === '') {
            \wp_die('Missing `ssh` value for prod operations.');
        }

        if (!in_array($runMode, ['preview', 'run'], true)) {
            \wp_die('Invalid run mode.');
        }

        $siteRoot = self::get_site_root();

        $jobId = self::maybe_execute(
            $siteRoot,
            $actionType,
            $from,
            $to,
            $ssh,
            $compress,
            $excludeTables,
            $runMode
        );

        $redirect = \admin_url('admin.php?page=dbsync');
        if ($jobId !== '') {
            $redirect = \add_query_arg(['job_id' => $jobId], $redirect);
        }
        if (!\wp_safe_redirect($redirect)) {
            \wp_die('Redirect failed.');
        }
        \wp_safe_redirect($redirect);
        \exit;
    }

    private static function maybe_execute(
        string $siteRoot,
        string $actionType,
        string $from,
        string $to,
        string $ssh,
        bool $compress,
        string $excludeTables,
        string $runMode
    ): string {
        $jobsDir = self::get_jobs_dir();
        if (!\is_dir($jobsDir)) {
            \wp_mkdir_p($jobsDir);
        }

        $jobId = 'job_' . \bin2hex(\random_bytes(6));
        $logPath = $jobsDir . DIRECTORY_SEPARATOR . $jobId . '.log';
        $metaPath = $jobsDir . DIRECTORY_SEPARATOR . $jobId . '.json';

        $logLines = [];

        // When prod is involved, preflight the SSH connection from the PHP/web user.
        // This avoids the vague WP-CLI error and confirms that the current user has SSH keys/config.
        if (($from === 'prod' || $to === 'prod') && $ssh !== '') {
            $sshPreflight = self::ssh_preflight($ssh);
            $logLines[] = $sshPreflight['cmd'];
            $logLines = array_merge($logLines, $sshPreflight['output']);

            if ($sshPreflight['exit_code'] !== 0) {
                \file_put_contents($logPath, implode("\n", $logLines));
                \file_put_contents($metaPath, \wp_json_encode(['exit_code' => $sshPreflight['exit_code']]));
                return $jobId;
            }
        }

        $command = self::build_wp_command(
            $siteRoot,
            $actionType,
            $from,
            $to,
            $ssh,
            $compress,
            $excludeTables,
            $runMode === 'run'
        );

        // Preview and run both execute in the background so the job page can poll the log and show a live progress bar.
        if ($logLines !== []) {
            \file_put_contents($logPath, implode("\n", $logLines) . "\n");
        }

        $pathPrefix = 'PATH="/usr/local/bin:/usr/bin:/bin:${PATH}" ';
        $bgCmd = \sprintf(
            'cd %s && %snohup %s >> %s 2>&1 & echo $!',
            \escapeshellarg($siteRoot),
            $pathPrefix,
            self::shell_escape_command_without_cd($command),
            \escapeshellarg($logPath)
        );

        $pid = '';
        $exitCode = 0;
        $out = [];
        \exec($bgCmd, $out, $exitCode);
        if (!empty($out[0])) {
            $pid = (string) $out[0];
        }

        \file_put_contents($metaPath, \wp_json_encode([
            'exit_code' => null,
            'pid' => $pid,
            'command' => $command,
            'action_type' => $actionType,
            'run_mode' => $runMode,
        ]));

        return $jobId;
    }

    private static function ssh_preflight(string $ssh): array
    {
        // Attempt a simple SSH command using the same user as the PHP/web process.
        // If it fails, we surface the SSH error instead of WP-CLI's generic message.
        $userHost = self::ssh_user_host($ssh);
        $cmd = 'ssh -o BatchMode=yes -o StrictHostKeyChecking=no -o ConnectTimeout=10 '
            . \escapeshellarg($userHost)
            . ' true';

        $outputLines = [];
        $exitCode = 0;
        \exec($cmd . ' 2>&1', $outputLines, $exitCode);

        return [
            'cmd' => $cmd,
            'output' => $outputLines,
            'exit_code' => $exitCode,
        ];
    }

    private static function ssh_user_host(string $ssh): string
    {
        $ssh = \trim($ssh);
        // Strip WP-CLI's extended syntax "...~/path" or ".../path" down to "user@host[:port]".
        $tildePos = \strpos($ssh, '~');
        if ($tildePos !== false) {
            $ssh = \substr($ssh, 0, $tildePos);
        }

        $slashPos = \strpos($ssh, '/');
        if ($slashPos !== false) {
            $ssh = \substr($ssh, 0, $slashPos);
        }

        $tokens = \preg_split('/\s+/', $ssh);
        return isset($tokens[0]) ? (string) $tokens[0] : '';
    }

    private static function sanitize_ssh_param(string $ssh): string
    {
        $ssh = \trim($ssh);
        // WP-CLI `--ssh` must not contain whitespace.
        if (\preg_match('/\s/', $ssh) === 1) {
            \wp_die('Invalid SSH value: no spaces allowed.');
        }

        // Allows: user@host, optional :port, optional ~ or / path suffix.
        if (\preg_match('/^[A-Za-z0-9_.:@~\\/-]+$/', $ssh) !== 1) {
            \wp_die('Invalid SSH value: contains unsupported characters.');
        }

        return $ssh;
    }

    private static function ssh_remote_user(string $ssh): string
    {
        $atPos = \strpos($ssh, '@');
        if ($atPos === false) {
            return '';
        }
        return \strtolower(\substr($ssh, 0, $atPos));
    }

    private static function escape_cli_value(string $value): string
    {
        return \escapeshellarg($value);
    }

    private static function build_wp_command(
        string $siteRoot,
        string $actionType,
        string $from,
        string $to,
        string $ssh,
        bool $compress,
        string $excludeTables,
        bool $run
    ): string {
        // We build a command *without* `cd` so it can be reused for preview/background.
        $wpBin = 'wp';

        $remoteSshArg = '';
        if ($from === 'prod' || $to === 'prod') {
            $sanitized = self::sanitize_ssh_param($ssh);
            // IMPORTANT: don't pass WP-CLI global `--ssh`. It would run `dbsync` on the remote server.
            // We pass it to our plugin as `--remote-ssh` instead, and the plugin itself uses WP-CLI `--ssh`
            // only for core commands that do not require this plugin to be installed on prod.
            $remoteSshArg = ' --remote-ssh=' . escapeshellarg($sanitized);
        }

        $compressArg = $compress ? ' --compress' : '';
        $excludeArg = $excludeTables !== '' ? (' --exclude-tables=' . self::escape_cli_value($excludeTables)) : '';
        $runArg = $run ? ' --run' : '';

        if ($actionType === 'media-sync') {
            return $wpBin
                . ' dbsync media_sync'
                . ' --from=' . escapeshellarg($from)
                . ' --to=' . escapeshellarg($to)
                . $remoteSshArg
                . $runArg;
        }

        // Default to DB sync.
        return $wpBin
            . ' dbsync sync'
            . ' --from=' . escapeshellarg($from)
            . ' --to=' . escapeshellarg($to)
            . $remoteSshArg
            . $compressArg
            . $excludeArg
            . $runArg;
    }

    private static function shell_escape_command_without_cd(string $command): string
    {
        // `$command` is already a string with proper quoting for arguments.
        // We only escape the whole string for `nohup ... <cmd>`.
        // Note: `nohup` expects the command as a single shell string, so we return as-is.
        return $command;
    }

    private static function get_site_root(): string
    {
        // We need the directory that contains `wp-cli.yml` (for Bedrock this is `site/`).
        // Find it by walking up from this file.
        $dir = \dirname(__DIR__);
        while (true) {
            $candidate = $dir . DIRECTORY_SEPARATOR . 'wp-cli.yml';
            if (\file_exists($candidate)) {
                return $dir;
            }
            $parent = \dirname($dir);
            if ($parent === $dir) {
                // Fallback: original expected relative location.
                return \dirname(__DIR__, 6);
            }
            $dir = $parent;
        }
    }

    private static function get_jobs_dir(): string
    {
        $base = defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : ABSPATH . 'wp-content';
        return $base . DIRECTORY_SEPARATOR . 'dbsync-jobs';
    }
}

