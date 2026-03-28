<?php

namespace BrandonRP\DBSync\Admin;

use BrandonRP\DBSync\Admin\AdminPage;

final class AdminHooks
{
    public static function init(): void
    {
        \add_action('admin_menu', [__CLASS__, 'register_menu']);
        \add_action('admin_post_dbsync_action', [__CLASS__, 'handle_action']);
        \add_action('admin_post_dbsync_download_export', [__CLASS__, 'download_export']);
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
        $runMode = (string) ($_POST['dbsync_run_mode'] ?? 'run'); // preview|run

        $from = strtolower((string) ($_POST['from'] ?? 'prod'));
        $to = strtolower((string) ($_POST['to'] ?? 'local'));

        $ssh = (string) ($_POST['remote_ssh'] ?? ($_POST['remote_ssh_media'] ?? ''));
        $compress = !empty($_POST['compress']);

        $excludeTables = (string) ($_POST['exclude_tables'] ?? '');
        $excludeTables = trim($excludeTables);
        $excludeTables = $excludeTables === '' ? '' : $excludeTables;

        if (!in_array($runMode, ['preview', 'run'], true)) {
            \wp_die('Invalid run mode.');
        }

        if ($actionType === 'db-export') {
            $siteRoot = self::get_site_root();
            $jobId = self::maybe_execute(
                $siteRoot,
                'db-export',
                'local',
                'local',
                '',
                $compress,
                $excludeTables,
                $runMode
            );
        } elseif ($actionType === 'db-import') {
            $stagingPath = self::save_import_upload();
            $beforeBackup = !empty($_POST['import_before_backup']);
            $replaceUrls = !empty($_POST['import_replace_urls']);
            $urlHomeFrom = null;
            $urlHomeTo = null;
            $urlSiteFrom = null;
            $urlSiteTo = null;
            if ($replaceUrls) {
                $urlHomeFrom = trim((string) ($_POST['import_url_home_from'] ?? ''));
                $urlHomeTo = trim((string) ($_POST['import_url_home_to'] ?? ''));
                $urlSiteFrom = trim((string) ($_POST['import_url_siteurl_from'] ?? ''));
                $urlSiteTo = trim((string) ($_POST['import_url_siteurl_to'] ?? ''));
                if ($urlHomeFrom === '' || $urlHomeTo === '') {
                    \wp_die('When replacing URLs, enter both the old and new Site Address (home) values.');
                }
                if (($urlSiteFrom === '') !== ($urlSiteTo === '')) {
                    \wp_die('Enter both old and new WordPress Address (siteurl), or leave both blank.');
                }
            }
            $siteRoot = self::get_site_root();
            $jobId = self::maybe_execute(
                $siteRoot,
                'db-import',
                'local',
                'local',
                '',
                false,
                '',
                $runMode,
                $stagingPath,
                $beforeBackup,
                $replaceUrls ? $urlHomeFrom : null,
                $replaceUrls ? $urlHomeTo : null,
                ($replaceUrls && $urlSiteFrom !== '') ? $urlSiteFrom : null,
                ($replaceUrls && $urlSiteTo !== '') ? $urlSiteTo : null
            );
        } else {
            if (!in_array($from, ['prod', 'local'], true) || !in_array($to, ['prod', 'local'], true)) {
                \wp_die('Invalid from/to values.');
            }

            if (($from === 'prod' || $to === 'prod') && $ssh === '') {
                \wp_die('Missing `ssh` value for prod operations.');
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
        }

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
        string $runMode,
        ?string $importInputPath = null,
        bool $importBeforeBackup = false,
        ?string $importReplaceHomeFrom = null,
        ?string $importReplaceHomeTo = null,
        ?string $importReplaceSiteurlFrom = null,
        ?string $importReplaceSiteurlTo = null
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
                self::prune_stored_jobs();

                return $jobId;
            }
        }

        $exportOutputPath = null;
        $exportFinalBasename = null;
        if ($actionType === 'db-export') {
            $exportsDir = self::get_exports_dir();
            if (!\is_dir($exportsDir)) {
                \wp_mkdir_p($exportsDir);
            }
            $indexFile = $exportsDir . DIRECTORY_SEPARATOR . 'index.php';
            if (!\file_exists($indexFile)) {
                \file_put_contents($indexFile, "<?php\n// Silence is golden.\n");
            }
            $sqlBasename = 'dbsync-local-' . gmdate('Ymd-His') . '.sql';
            $exportOutputPath = $exportsDir . DIRECTORY_SEPARATOR . $sqlBasename;
            $exportFinalBasename = $compress ? ($sqlBasename . '.gz') : $sqlBasename;
        }

        $command = self::build_wp_command(
            $siteRoot,
            $actionType,
            $from,
            $to,
            $ssh,
            $compress,
            $excludeTables,
            $runMode === 'run',
            $exportOutputPath,
            $importInputPath,
            $importBeforeBackup,
            $importReplaceHomeFrom,
            $importReplaceHomeTo,
            $importReplaceSiteurlFrom,
            $importReplaceSiteurlTo
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

        $meta = [
            'exit_code' => null,
            'pid' => $pid,
            'command' => $command,
            'action_type' => $actionType,
            'run_mode' => $runMode,
        ];
        if ($exportFinalBasename !== null) {
            $meta['export_final_basename'] = $exportFinalBasename;
        }
        \file_put_contents($metaPath, \wp_json_encode($meta));

        self::prune_stored_jobs();

        return $jobId;
    }

    /**
     * Remove oldest job .json/.log pairs under wp-content/dbsync-jobs/ when over the limit.
     *
     * Limit: apply_filters( 'dbsync_max_stored_jobs', 25 ). Use 0 for unlimited.
     */
    public static function prune_stored_jobs(): void
    {
        self::prune_jobs_directory(self::get_jobs_dir());
    }

    private static function prune_jobs_directory(string $jobsDir): void
    {
        if (!\is_dir($jobsDir)) {
            return;
        }

        $maxKeep = (int) \apply_filters('dbsync_max_stored_jobs', 25);
        if ($maxKeep < 1) {
            return;
        }

        $pattern = $jobsDir . DIRECTORY_SEPARATOR . 'job_*.json';
        $metaFiles = \glob($pattern);
        if ($metaFiles === false || \count($metaFiles) <= $maxKeep) {
            return;
        }

        $items = [];
        foreach ($metaFiles as $path) {
            $base = \basename($path);
            if (\preg_match('/^job_[a-f0-9]{12}\.json$/', $base) !== 1) {
                continue;
            }
            $mtime = @\filemtime($path);
            $items[] = [
                'path' => $path,
                'mtime' => $mtime !== false ? $mtime : 0,
            ];
        }

        if (\count($items) <= $maxKeep) {
            return;
        }

        \usort($items, static function (array $a, array $b): int {
            return $b['mtime'] <=> $a['mtime'];
        });

        foreach (\array_slice($items, $maxKeep) as $row) {
            $jobId = \basename($row['path'], '.json');
            @\unlink($row['path']);
            @\unlink($jobsDir . DIRECTORY_SEPARATOR . $jobId . '.log');
        }
    }

    public static function download_export(): void
    {
        if (!\current_user_can('manage_options')) {
            \wp_die('Forbidden', '', ['response' => 403]);
        }
        $nonce = isset($_GET['_wpnonce']) ? (string) $_GET['_wpnonce'] : '';
        if (!\wp_verify_nonce($nonce, 'dbsync_download_export')) {
            \wp_die('Invalid request.', '', ['response' => 403]);
        }
        $file = isset($_GET['file']) ? (string) $_GET['file'] : '';
        if (!self::is_safe_export_basename($file)) {
            \wp_die('Invalid file name.', '', ['response' => 400]);
        }
        $path = self::get_exports_dir() . DIRECTORY_SEPARATOR . $file;
        if (!\is_readable($path)) {
            \wp_die('File not found.', '', ['response' => 404]);
        }
        $size = \filesize($path);
        if ($size === false || $size < 1) {
            \wp_die('File not found.', '', ['response' => 404]);
        }

        $dlName = $file;
        \header('Content-Type: application/octet-stream');
        \header('Content-Disposition: attachment; filename="' . $dlName . '"');
        \header('Content-Length: ' . (string) $size);
        \header('X-Robots-Tag: noindex');
        if (\function_exists('readfile')) {
            \readfile($path);
        } else {
            echo \file_get_contents($path);
        }
        exit;
    }

    private static function is_safe_export_basename(string $name): bool
    {
        return \preg_match('/^dbsync-local-\d{8}-\d{6}\.sql(\.gz)?$/', $name) === 1;
    }

    private static function get_exports_dir(): string
    {
        $base = defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : \ABSPATH . 'wp-content';

        return $base . DIRECTORY_SEPARATOR . 'dbsync-exports';
    }

    private static function get_imports_staging_dir(): string
    {
        $base = defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : \ABSPATH . 'wp-content';

        return $base . DIRECTORY_SEPARATOR . 'dbsync-imports';
    }

    /**
     * Validate and move upload into wp-content/dbsync-imports/ for WP-CLI import.
     */
    private static function save_import_upload(): string
    {
        if (!isset($_FILES['dbsync_import_file']) || !\is_array($_FILES['dbsync_import_file'])) {
            \wp_die('Choose a database file to import (<code>.sql</code> or <code>.sql.gz</code>).');
        }

        $f = $_FILES['dbsync_import_file'];
        $err = (int) ($f['error'] ?? \UPLOAD_ERR_NO_FILE);
        if ($err === \UPLOAD_ERR_NO_FILE) {
            \wp_die('Choose a database file to import (<code>.sql</code> or <code>.sql.gz</code>).');
        }
        if ($err !== \UPLOAD_ERR_OK) {
            \wp_die('File upload failed (error code ' . $err . ').');
        }

        $tmp = isset($f['tmp_name']) ? (string) $f['tmp_name'] : '';
        if ($tmp === '' || !\is_uploaded_file($tmp)) {
            \wp_die('Invalid upload.');
        }

        $origName = isset($f['name']) ? (string) $f['name'] : '';
        $lower = \strtolower($origName);
        $isSqlGz = \str_ends_with($lower, '.sql.gz');
        $isSql = \str_ends_with($lower, '.sql') && !$isSqlGz;
        if (!$isSql && !$isSqlGz) {
            \wp_die('Only <code>.sql</code> or <code>.sql.gz</code> files are allowed.');
        }

        $dir = self::get_imports_staging_dir();
        if (!\is_dir($dir)) {
            \wp_mkdir_p($dir);
        }
        $indexFile = $dir . DIRECTORY_SEPARATOR . 'index.php';
        if (!\file_exists($indexFile)) {
            \file_put_contents($indexFile, "<?php\n// Silence is golden.\n");
        }

        $ext = $isSqlGz ? '.sql.gz' : '.sql';
        $destName = 'dbsync-import-' . gmdate('Ymd-His') . '-' . \bin2hex(\random_bytes(4)) . $ext;
        $destPath = $dir . DIRECTORY_SEPARATOR . $destName;

        if (!\move_uploaded_file($tmp, $destPath)) {
            \wp_die('Could not save the uploaded file.');
        }
        @\chmod($destPath, 0600);
        self::prune_staged_imports();

        return $destPath;
    }

    /**
     * Keep wp-content/dbsync-imports/ from growing without bound.
     *
     * Limit: apply_filters( 'dbsync_max_staged_imports', 8 ). Use 0 for unlimited.
     */
    private static function prune_staged_imports(): void
    {
        $dir = self::get_imports_staging_dir();
        if (!\is_dir($dir)) {
            return;
        }

        $maxKeep = (int) \apply_filters('dbsync_max_staged_imports', 8);
        if ($maxKeep < 1) {
            return;
        }

        $sql = \glob($dir . DIRECTORY_SEPARATOR . 'dbsync-import-*.sql');
        $sqlGz = \glob($dir . DIRECTORY_SEPARATOR . 'dbsync-import-*.sql.gz');
        $paths = \array_merge(
            \is_array($sql) ? $sql : [],
            \is_array($sqlGz) ? $sqlGz : []
        );
        if (\count($paths) <= $maxKeep) {
            return;
        }

        $items = [];
        foreach ($paths as $path) {
            $base = \basename($path);
            if (\preg_match('/^dbsync-import-\d{8}-\d{6}-[a-f0-9]{8}\.sql(\.gz)?$/', $base) !== 1) {
                continue;
            }
            $mtime = @\filemtime($path);
            $items[] = [
                'path' => $path,
                'mtime' => $mtime !== false ? $mtime : 0,
            ];
        }

        if (\count($items) <= $maxKeep) {
            return;
        }

        \usort($items, static function (array $a, array $b): int {
            return $b['mtime'] <=> $a['mtime'];
        });

        foreach (\array_slice($items, $maxKeep) as $row) {
            @\unlink($row['path']);
        }
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
        bool $run,
        ?string $exportOutputPath = null,
        ?string $importInputPath = null,
        bool $importBeforeBackup = false,
        ?string $importReplaceHomeFrom = null,
        ?string $importReplaceHomeTo = null,
        ?string $importReplaceSiteurlFrom = null,
        ?string $importReplaceSiteurlTo = null
    ): string {
        // We build a command *without* `cd` so it can be reused for preview/background.
        $wpBin = 'wp';

        $compressArg = $compress ? ' --compress' : '';
        $excludeArg = $excludeTables !== '' ? (' --exclude-tables=' . self::escape_cli_value($excludeTables)) : '';
        $runArg = $run ? ' --run' : '';

        if ($actionType === 'db-export') {
            if ($exportOutputPath === null || $exportOutputPath === '') {
                throw new \InvalidArgumentException('db-export requires an output path.');
            }

            return $wpBin
                . ' dbsync export'
                . ' --output=' . self::escape_cli_value($exportOutputPath)
                . $compressArg
                . $excludeArg
                . $runArg;
        }

        if ($actionType === 'db-import') {
            if ($importInputPath === null || $importInputPath === '') {
                throw new \InvalidArgumentException('db-import requires an input path.');
            }

            $backupArg = $importBeforeBackup ? ' --before-backup' : '';
            $urlArg = '';
            if ($importReplaceHomeFrom !== null && $importReplaceHomeFrom !== ''
                && $importReplaceHomeTo !== null && $importReplaceHomeTo !== '') {
                $urlArg .= ' --replace-url-home-from=' . self::escape_cli_value($importReplaceHomeFrom)
                    . ' --replace-url-home-to=' . self::escape_cli_value($importReplaceHomeTo);
            }
            if ($importReplaceSiteurlFrom !== null && $importReplaceSiteurlFrom !== ''
                && $importReplaceSiteurlTo !== null && $importReplaceSiteurlTo !== '') {
                $urlArg .= ' --replace-url-siteurl-from=' . self::escape_cli_value($importReplaceSiteurlFrom)
                    . ' --replace-url-siteurl-to=' . self::escape_cli_value($importReplaceSiteurlTo);
            }

            return $wpBin
                . ' dbsync import'
                . ' --input=' . self::escape_cli_value($importInputPath)
                . $backupArg
                . $urlArg
                . $runArg;
        }

        $remoteSshArg = '';
        if ($from === 'prod' || $to === 'prod') {
            $sanitized = self::sanitize_ssh_param($ssh);
            // IMPORTANT: don't pass WP-CLI global `--ssh`. It would run `dbsync` on the remote server.
            // We pass it to our plugin as `--remote-ssh` instead, and the plugin itself uses WP-CLI `--ssh`
            // only for core commands that do not require this plugin to be installed on prod.
            $remoteSshArg = ' --remote-ssh=' . escapeshellarg($sanitized);
        }

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

