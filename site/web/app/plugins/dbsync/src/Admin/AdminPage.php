<?php

namespace BrandonRP\DBSync\Admin;

final class AdminPage
{
    public static function render(): void
    {
        if (!\current_user_can('manage_options')) {
            \wp_die('Insufficient permissions.');
        }

        $jobId = isset($_GET['job_id']) ? (string) $_GET['job_id'] : '';

        echo '<div class="wrap">';
        echo '<h1>DB Sync</h1>';

        self::render_diagnostics_panel();

        self::render_job_panel($jobId);

        self::render_forms_layout_styles();

        echo '<div class="dbsync-forms-grid">';
        echo '<div class="dbsync-form-panel">';
        echo '<h2>Database Sync</h2>';
        self::render_db_form();
        echo '</div>';
        echo '<div class="dbsync-form-panel">';
        echo '<h2>Media Sync</h2>';
        self::render_media_form();
        echo '</div>';
        echo '</div>';

        echo '</div>';
    }

    private static function render_forms_layout_styles(): void
    {
        ?>
<style>
.dbsync-forms-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 24px;
    margin-top: 24px;
    align-items: start;
    max-width: 100%;
}
.dbsync-form-panel {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    padding: 16px 20px 20px;
    box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04);
    min-width: 0;
}
.dbsync-form-panel h2 {
    margin: 0 0 12px;
    padding-bottom: 10px;
    border-bottom: 1px solid #dcdcde;
    font-size: 1.15em;
}
.dbsync-form-panel .form-table th {
    width: 120px;
    padding-left: 0;
}
.dbsync-form-panel .form-table td .regular-text {
    max-width: 100%;
    box-sizing: border-box;
}
@media screen and (max-width: 1100px) {
    .dbsync-forms-grid {
        grid-template-columns: 1fr;
    }
}
</style>
        <?php
    }

    private static function render_diagnostics_panel(): void
    {
        $user = \function_exists('get_current_user') ? \get_current_user() : '';
        $euid = \function_exists('posix_geteuid') ? \posix_geteuid() : '';
        $home = (string) (\getenv('HOME') ?: '');
        $sshSock = (string) (\getenv('SSH_AUTH_SOCK') ?: '');

        $knownHostsPath = '';
        $knownHostsExists = false;
        if ($home !== '') {
            $knownHostsPath = $home . '/.ssh/known_hosts';
            $knownHostsExists = \file_exists($knownHostsPath);
        }

        echo '<h2 style="margin-top:22px;">Diagnostics</h2>';
        echo '<div class="notice notice-info">';
        echo '<p><strong>PHP user:</strong> ' . \esc_html($user) . ' &nbsp; <strong>euid:</strong> ' . \esc_html((string) $euid) . '</p>';
        echo '<p><strong>HOME:</strong> ' . \esc_html($home) . '</p>';
        echo '<p><strong>SSH_AUTH_SOCK:</strong> ' . \esc_html($sshSock) . '</p>';
        echo '<p><strong>known_hosts:</strong> ' . \esc_html($knownHostsPath) . ' (' . (\esc_html($knownHostsExists ? 'present' : 'missing')) . ')</p>';
        echo '</div>';
    }

    private static function render_db_form(): void
    {
        $pageUrl = \admin_url('admin-post.php');
        echo '<form method="post" action="' . \esc_url($pageUrl) . '">';
        echo '<input type="hidden" name="action" value="dbsync_action" />';
        echo '<input type="hidden" name="dbsync_action_type" value="dbsync" />';
        \wp_nonce_field('dbsync_action', '_wpnonce');

        echo '<table class="form-table" role="presentation">';
        echo '<tr><th scope="row"><label for="from">From</label></th><td>';
        echo '<select name="from" id="from">';
        self::option('prod', 'Production', false);
        self::option('local', 'Local', true);
        echo '</select>';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="to">To</label></th><td>';
        echo '<select name="to" id="to">';
        self::option('prod', 'Production', true);
        self::option('local', 'Local', false);
        echo '</select>';
        echo '</td></tr>';

        $defaultRemoteSsh = 'root@64.23.203.166~/srv/www/brandonrp.com/current';
        echo '<tr><th scope="row"><label for="remote_ssh">Remote SSH (required if prod)</label></th><td>';
        echo '<input type="text" name="remote_ssh" id="remote_ssh" class="regular-text" value="' . \esc_attr($defaultRemoteSsh) . '" placeholder="' . \esc_attr($defaultRemoteSsh) . '" />';
        echo self::remote_ssh_help_text();
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="compress">Compress dump</label></th><td>';
        echo '<label><input type="checkbox" name="compress" id="compress" value="1" checked /> Enable gzip</label>';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="exclude_tables">Exclude tables (comma-separated)</label></th><td>';
        echo '<input type="text" name="exclude_tables" id="exclude_tables" class="regular-text" placeholder="wp_options,wp_users" />';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="dbsync_run_mode">Run mode</label></th><td>';
        echo '<select name="dbsync_run_mode" id="dbsync_run_mode">';
        echo '<option value="preview" selected>Preview (dry-run planning)</option>';
        echo '<option value="run">Run (execute + import)</option>';
        echo '</select>';
        echo '</td></tr>';
        echo '</table>';

        echo '<p><button type="submit" class="button button-primary">Submit</button></p>';
        echo '</form>';
    }

    private static function render_media_form(): void
    {
        $pageUrl = \admin_url('admin-post.php');
        echo '<form method="post" action="' . \esc_url($pageUrl) . '">';
        echo '<input type="hidden" name="action" value="dbsync_action" />';
        echo '<input type="hidden" name="dbsync_action_type" value="media-sync" />';
        \wp_nonce_field('dbsync_action', '_wpnonce');

        echo '<table class="form-table" role="presentation">';
        echo '<tr><th scope="row"><label for="from_media">From</label></th><td>';
        echo '<select name="from" id="from_media">';
        self::option('prod', 'Production', false);
        self::option('local', 'Local', true);
        echo '</select>';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="to_media">To</label></th><td>';
        echo '<select name="to" id="to_media">';
        self::option('prod', 'Production', true);
        self::option('local', 'Local', false);
        echo '</select>';
        echo '</td></tr>';

        $defaultRemoteSsh = 'root@64.23.203.166~/srv/www/brandonrp.com/current';
        echo '<tr><th scope="row"><label for="remote_ssh_media">Remote SSH (required if prod)</label></th><td>';
        echo '<input type="text" name="remote_ssh" id="remote_ssh_media" class="regular-text" value="' . \esc_attr($defaultRemoteSsh) . '" placeholder="' . \esc_attr($defaultRemoteSsh) . '" />';
        echo self::remote_ssh_help_text();
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="media_run_mode">Run mode</label></th><td>';
        echo '<select name="dbsync_run_mode" id="media_run_mode">';
        echo '<option value="preview" selected>Preview (dry-run)</option>';
        echo '<option value="run">Run (rsync uploads)</option>';
        echo '</select>';
        echo '</td></tr>';
        echo '</table>';

        echo '<p><button type="submit" class="button button-primary">Submit</button></p>';
        echo '</form>';
    }

    /**
     * Progress bar title: dry-run vs execute, DB vs media.
     */
    private static function progress_label(string $actionType, string $runMode): string
    {
        $preview = ($runMode === 'preview');
        if ($actionType === 'media-sync') {
            return $preview ? 'Planning media sync…' : 'Syncing media…';
        }
        return $preview ? 'Planning dry run…' : 'Syncing database…';
    }

    /**
     * Help text for WP-CLI style `user@host~/path` (Bedrock root = directory containing wp-cli.yml).
     */
    private static function remote_ssh_help_text(): string
    {
        return '<p class="description">Format: <code>user@host~/absolute/path/to/bedrock-root</code> (Trellis: often <code>/srv/www/your-site/current</code>). '
            . 'The path after <code>~</code> must exist on the server. If you see <code>cd: … No such file or directory</code>, SSH in and run '
            . '<code>find /srv/www -name wp-cli.yml 2>/dev/null</code> (or <code>ls -la /srv/www/</code>) and paste the directory that contains <code>wp-cli.yml</code> here.</p>';
    }

    private static function option(string $value, string $label, bool $selected): void
    {
        echo '<option value="' . \esc_attr($value) . '"' . ($selected ? ' selected' : '') . '>' . \esc_html($label) . '</option>';
    }

    private static function render_job_panel(string $jobId): void
    {
        if ($jobId === '') {
            echo '<p style="margin-top:8px;color:#666;">No job selected. Preview a sync first, then run.</p>';
            return;
        }

        $jobsDir = self::get_jobs_dir();
        $metaPath = $jobsDir . DIRECTORY_SEPARATOR . $jobId . '.json';
        $logPath = $jobsDir . DIRECTORY_SEPARATOR . $jobId . '.log';

        echo '<h2 style="margin-top:18px;">Job Status</h2>';

        if (!\file_exists($metaPath) && !\file_exists($logPath)) {
            echo '<div class="notice notice-error"><p>Unknown job: ' . \esc_html($jobId) . '</p></div>';
            return;
        }

        $meta = [];
        if (\file_exists($metaPath)) {
            $raw = \file_get_contents($metaPath);
            $decoded = \json_decode((string) $raw, true);
            if (\is_array($decoded)) {
                $meta = $decoded;
            }
        }

        $pid = isset($meta['pid']) ? (string) $meta['pid'] : '';
        $exitCode = $meta['exit_code'] ?? null;

        $isRunning = false;
        if ($pid !== '' && ctype_digit($pid) && \function_exists('posix_kill')) {
            $isRunning = \posix_kill((int) $pid, 0);
        }

        $statusLabel = $isRunning ? 'Running' : 'Finished';
        $nonce = \wp_create_nonce('dbsync_job_status');
        $actionType = isset($meta['action_type']) ? (string) $meta['action_type'] : 'dbsync';
        $runModeMeta = isset($meta['run_mode']) ? (string) $meta['run_mode'] : 'run';
        $progressLabel = self::progress_label($actionType, $runModeMeta);

        echo '<div id="dbsync-job-panel" class="dbsync-job-panel" data-job-id="' . \esc_attr($jobId) . '" data-running="' . ($isRunning ? '1' : '0') . '" data-nonce="' . \esc_attr($nonce) . '">';

        echo '<div id="dbsync-progress-wrap" class="dbsync-progress-wrap" style="' . ($isRunning ? '' : 'display:none;') . '">';
        echo '<p class="dbsync-progress-text" id="dbsync-progress-text">' . \esc_html($progressLabel) . '</p>';
        echo '<div class="dbsync-progress-bar"><div class="dbsync-progress-inner"></div></div>';
        echo '</div>';

        echo '<div id="dbsync-job-status" class="notice notice-info"><p><strong>Job:</strong> ' . \esc_html($jobId) . ' &nbsp; <strong>Status:</strong> <span id="dbsync-status-label">' . \esc_html($statusLabel) . '</span></p></div>';

        if ($exitCode !== null) {
            echo '<p id="dbsync-exit-wrap"><strong>Exit code:</strong> <span id="dbsync-exit-code">' . \esc_html((string) $exitCode) . '</span></p>';
        } else {
            echo '<p id="dbsync-exit-wrap" style="display:none;"><strong>Exit code:</strong> <span id="dbsync-exit-code"></span></p>';
        }

        if (\file_exists($logPath)) {
            $tail = self::tail_file($logPath, 200000);
            echo '<h3 style="margin-top:16px;">Log</h3>';
            echo '<pre id="dbsync-job-log" style="background:#f6f7f7;padding:12px;max-height:420px;overflow:auto;">' . \esc_html($tail) . '</pre>';
        } else {
            echo '<h3 style="margin-top:16px;">Log</h3>';
            echo '<pre id="dbsync-job-log" style="background:#f6f7f7;padding:12px;max-height:420px;overflow:auto;">No log yet.</pre>';
        }

        echo '</div>';

        self::render_job_panel_script();
    }

    private static function render_job_panel_script(): void
    {
        ?>
<style>
.dbsync-progress-wrap { margin: 12px 0 16px; padding: 12px 16px; background: #f0f6fc; border-left: 4px solid #2271b1; border-radius: 2px; }
.dbsync-progress-text { margin: 0 0 8px; font-weight: 600; color: #1d2327; }
.dbsync-progress-bar { height: 8px; background: #c3c4c7; border-radius: 4px; overflow: hidden; }
.dbsync-progress-inner { height: 100%; width: 30%; background: #2271b1; border-radius: 4px; animation: dbsync-progress 1.4s ease-in-out infinite; }
@keyframes dbsync-progress { 0% { margin-left: 0; } 50% { margin-left: 70%; } 100% { margin-left: 0; } }
</style>
<script>
(function() {
    var panel = document.getElementById('dbsync-job-panel');
    if (!panel) return;
    var jobId = panel.getAttribute('data-job-id');
    var nonce = panel.getAttribute('data-nonce');
    var running = panel.getAttribute('data-running') === '1';
    var progressWrap = document.getElementById('dbsync-progress-wrap');
    var statusLabel = document.getElementById('dbsync-status-label');
    var exitWrap = document.getElementById('dbsync-exit-wrap');
    var exitCodeEl = document.getElementById('dbsync-exit-code');
    var logEl = document.getElementById('dbsync-job-log');

    if (!running) return;

    var poll = function() {
        var xhr = new XMLHttpRequest();
        xhr.open('GET', '<?php echo \esc_js(\admin_url('admin-ajax.php')); ?>?action=dbsync_job_status&job_id=' + encodeURIComponent(jobId) + '&_wpnonce=' + encodeURIComponent(nonce));
        xhr.onreadystatechange = function() {
            if (xhr.readyState !== 4) return;
            try {
                var res = JSON.parse(xhr.responseText);
                if (!res.success || !res.data) return;
                var data = res.data;
                if (statusLabel) statusLabel.textContent = data.running ? 'Running' : 'Finished';
                if (progressWrap) progressWrap.style.display = data.running ? '' : 'none';
                if (data.exit_code !== null && data.exit_code !== undefined) {
                    if (exitWrap) { exitWrap.style.display = ''; if (exitCodeEl) exitCodeEl.textContent = String(data.exit_code); }
                }
                if (logEl && typeof data.log === 'string') {
                    logEl.textContent = data.log;
                    logEl.scrollTop = logEl.scrollHeight;
                }
                if (!data.running) clearInterval(interval);
            } catch (e) {}
        };
        xhr.send();
    };

    var interval = setInterval(poll, 1000);
    poll();
})();
</script>
        <?php
    }

    /**
     * Return current job status for AJAX polling. Keys: running (bool), exit_code (int|null), log (string).
     */
    public static function get_job_status(string $jobId): array
    {
        $jobsDir = self::get_jobs_dir();
        $metaPath = $jobsDir . DIRECTORY_SEPARATOR . $jobId . '.json';
        $logPath = $jobsDir . DIRECTORY_SEPARATOR . $jobId . '.log';

        $running = false;
        $exitCode = null;
        $log = '';

        if (\file_exists($metaPath)) {
            $raw = \file_get_contents($metaPath);
            $decoded = \json_decode((string) $raw, true);
            if (\is_array($decoded)) {
                $exitCode = isset($decoded['exit_code']) ? $decoded['exit_code'] : null;
                $pid = isset($decoded['pid']) ? (string) $decoded['pid'] : '';
                if ($pid !== '' && ctype_digit($pid) && \function_exists('posix_kill')) {
                    $running = \posix_kill((int) $pid, 0);
                }
            }
        }

        if (\file_exists($logPath)) {
            $log = self::tail_file($logPath, 200000);
        }

        return ['running' => $running, 'exit_code' => $exitCode, 'log' => $log];
    }

    private static function get_jobs_dir(): string
    {
        $base = defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : \ABSPATH . 'wp-content';
        return $base . DIRECTORY_SEPARATOR . 'dbsync-jobs';
    }

    private static function tail_file(string $path, int $maxBytes): string
    {
        $size = \filesize($path);
        if ($size === false || $size <= 0) {
            return '';
        }

        $start = $size > $maxBytes ? ($size - $maxBytes) : 0;
        $fp = \fopen($path, 'rb');
        if ($fp === false) {
            return '';
        }

        if ($start > 0) {
            \fseek($fp, $start);
        }
        $data = \stream_get_contents($fp);
        \fclose($fp);

        return (string) $data;
    }
}

