<?php

namespace BrandonRP\DBSync\Admin;

final class AdminPage
{
    public static function render(): void
    {
        if (!\current_user_can('manage_options')) {
            \wp_die('Insufficient permissions.');
        }

        AdminHooks::prune_stored_jobs();

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

        echo '<div class="dbsync-form-panel dbsync-export-panel">';
        echo '<h2>Local database export / import</h2>';
        self::render_export_import_forms();
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
.dbsync-export-panel {
    margin-top: 24px;
    max-width: 100%;
}
.dbsync-subsection-title {
    margin: 0 0 10px;
    font-size: 1.05em;
    font-weight: 600;
}
.dbsync-export-import-divider {
    margin: 20px 0;
    border: none;
    border-top: 1px solid #dcdcde;
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
        echo '<option value="run" selected>Run (execute + import)</option>';
        echo '<option value="preview">Preview (dry-run)</option>';
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
        echo '<option value="run" selected>Run (rsync uploads)</option>';
        echo '<option value="preview">Preview (dry-run)</option>';
        echo '</select>';
        echo '</td></tr>';
        echo '</table>';

        echo '<p><button type="submit" class="button button-primary">Submit</button></p>';
        echo '</form>';
    }

    private static function render_export_import_forms(): void
    {
        self::render_local_export_form();
        echo '<hr class="dbsync-export-import-divider" />';
        self::render_local_import_form();
    }

    private static function render_local_export_form(): void
    {
        $pageUrl = \admin_url('admin-post.php');
        echo '<h3 class="dbsync-subsection-title">Export</h3>';
        echo '<form method="post" action="' . \esc_url($pageUrl) . '">';
        echo '<input type="hidden" name="action" value="dbsync_action" />';
        echo '<input type="hidden" name="dbsync_action_type" value="db-export" />';
        \wp_nonce_field('dbsync_action', '_wpnonce');

        echo '<p class="description" style="margin-top:0;">'
            . 'Dump the <strong>current</strong> site database to <code>wp-content/dbsync-exports/</code> using the same scope as <code>wp dbsync export</code> (all tables with the site prefix plus other custom tables). '
            . 'After a successful <strong>Run</strong>, download the file below.</p>';

        echo '<table class="form-table" role="presentation">';
        echo '<tr><th scope="row"><label for="compress_export">Compress</label></th><td>';
        echo '<label><input type="checkbox" name="compress" id="compress_export" value="1" /> Gzip (<code>.sql.gz</code>)</label>';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="exclude_tables_export">Exclude tables (comma-separated)</label></th><td>';
        echo '<input type="text" name="exclude_tables" id="exclude_tables_export" class="regular-text" placeholder="wp_actionscheduler_actions" />';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="export_run_mode">Run mode</label></th><td>';
        echo '<select name="dbsync_run_mode" id="export_run_mode">';
        echo '<option value="run" selected>Run (write SQL file)</option>';
        echo '<option value="preview">Preview (dry-run)</option>';
        echo '</select>';
        echo '</td></tr>';
        echo '</table>';

        echo '<p><button type="submit" class="button button-primary">Export</button></p>';
        echo '</form>';
    }

    private static function render_local_import_form(): void
    {
        $pageUrl = \admin_url('admin-post.php');
        echo '<h3 class="dbsync-subsection-title">Import</h3>';
        echo '<form method="post" enctype="multipart/form-data" action="' . \esc_url($pageUrl) . '">';
        echo '<input type="hidden" name="action" value="dbsync_action" />';
        echo '<input type="hidden" name="dbsync_action_type" value="db-import" />';
        \wp_nonce_field('dbsync_action', '_wpnonce');

        echo '<p class="description" style="margin-top:0;">'
            . 'Upload a <code>.sql</code> or <code>.sql.gz</code> dump. This uses <code>wp dbsync import</code> (same as <code>wp db import</code> after optional gzip handling). '
            . '<strong>Run</strong> replaces the current database—use Preview (dry-run) first. Large files require sufficient PHP <code>upload_max_filesize</code> / <code>post_max_size</code>.</p>';

        echo '<table class="form-table" role="presentation">';
        echo '<tr><th scope="row"><label for="dbsync_import_file">SQL file</label></th><td>';
        echo '<input type="file" name="dbsync_import_file" id="dbsync_import_file" accept=".sql,.gz,application/gzip,application/x-gzip" required />';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="import_before_backup">Backup first</label></th><td>';
        echo '<label><input type="checkbox" name="import_before_backup" id="import_before_backup" value="1" /> Export current DB to <code>/tmp</code> before import (via <code>--before-backup</code>)</label>';
        echo '</td></tr>';

        $homeNow = (string) \get_option('home', '');
        $siteurlNow = (string) \get_option('siteurl', '');
        echo '<tr><th scope="row"><label for="import_replace_urls">Replace URLs</label></th><td>';
        echo '<label><input type="checkbox" name="import_replace_urls" id="import_replace_urls" value="1" /> After import, run <code>wp search-replace</code> (same as full DB sync: <code>--all-tables --precise</code>), then <code>wp cache flush</code></label>';
        echo '<p class="description" style="margin:8px 0 0;">Use the values from the <strong>source</strong> dump as &ldquo;old&rdquo; and this environment as &ldquo;new.&rdquo; If <code>home</code> and <code>siteurl</code> differ in the dump, fill both rows.</p>';
        echo '</td></tr>';

        echo '<tr class="dbsync-import-url-row"><th scope="row"><label for="import_url_home_from">Old Site Address (home)</label></th><td>';
        echo '<input type="url" name="import_url_home_from" id="import_url_home_from" class="regular-text" placeholder="https://production.example" value="" />';
        echo '</td></tr>';
        echo '<tr class="dbsync-import-url-row"><th scope="row"><label for="import_url_home_to">New Site Address (home)</label></th><td>';
        echo '<input type="url" name="import_url_home_to" id="import_url_home_to" class="regular-text" value="' . \esc_attr($homeNow) . '" />';
        echo '</td></tr>';
        echo '<tr class="dbsync-import-url-row"><th scope="row"><label for="import_url_siteurl_from">Old WordPress Address (siteurl)</label></th><td>';
        echo '<input type="url" name="import_url_siteurl_from" id="import_url_siteurl_from" class="regular-text" placeholder="Optional if same as home" value="" />';
        echo '</td></tr>';
        echo '<tr class="dbsync-import-url-row"><th scope="row"><label for="import_url_siteurl_to">New WordPress Address (siteurl)</label></th><td>';
        echo '<input type="url" name="import_url_siteurl_to" id="import_url_siteurl_to" class="regular-text" value="' . \esc_attr($siteurlNow) . '" />';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="import_run_mode">Run mode</label></th><td>';
        echo '<select name="dbsync_run_mode" id="import_run_mode">';
        echo '<option value="run" selected>Run (execute import)</option>';
        echo '<option value="preview">Preview (dry-run)</option>';
        echo '</select>';
        echo '</td></tr>';
        echo '</table>';

        echo '<p><button type="submit" class="button button-primary">Import</button></p>';
        echo '</form>';
    }

    /**
     * Progress bar title: dry-run vs execute, DB vs media.
     */
    private static function progress_label(string $actionType, string $runMode): string
    {
        $preview = ($runMode === 'preview');
        if ($actionType === 'media-sync') {
            return $preview ? 'Media sync (dry-run)…' : 'Syncing media…';
        }
        if ($actionType === 'db-export') {
            return $preview ? 'Database export (dry-run)…' : 'Exporting database…';
        }
        if ($actionType === 'db-import') {
            return $preview ? 'Database import (dry-run)…' : 'Importing database…';
        }
        return $preview ? 'Database sync (dry-run)…' : 'Syncing database…';
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
            echo '<p style="margin-top:8px;color:#666;">No job selected. Use Preview (dry-run), then Run when ready.</p>';
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

        $logContent = \file_exists($logPath) ? self::tail_file($logPath, 200000) : '';

        $initialPct = '';
        if ($actionType === 'media-sync' && $logContent !== '') {
            $p = self::parse_media_rsync_percent($logContent);
            if ($p !== null) {
                $initialPct = (string) $p;
            }
        }

        $outcomeData = self::compute_job_outcome($exitCode, $isRunning, $logContent);

        echo '<div id="dbsync-job-panel" class="dbsync-job-panel" data-job-id="' . \esc_attr($jobId) . '" data-running="' . ($isRunning ? '1' : '0') . '" data-nonce="' . \esc_attr($nonce) . '" data-action-type="' . \esc_attr($actionType) . '">';

        echo '<div id="dbsync-progress-wrap" class="dbsync-progress-wrap" style="' . ($isRunning ? '' : 'display:none;') . '">';
        echo '<p class="dbsync-progress-text" id="dbsync-progress-text">' . \esc_html($progressLabel);
        echo ' <span id="dbsync-media-percent-label" class="dbsync-media-percent"' . ($initialPct !== '' ? '' : ' style="display:none;"') . '>' . ($initialPct !== '' ? \esc_html($initialPct) . '%' : '') . '</span>';
        echo '</p>';
        $innerClass = 'dbsync-progress-inner' . ($initialPct !== '' ? ' dbsync-progress-determinate' : '');
        $innerStyle = $initialPct !== '' ? ' style="width:' . (int) $initialPct . '%;animation:none;margin-left:0;"' : '';
        echo '<div class="dbsync-progress-bar"><div class="' . \esc_attr($innerClass) . '" id="dbsync-progress-inner"' . $innerStyle . '></div></div>';
        echo '</div>';

        echo '<div id="dbsync-job-status" class="notice notice-info"><p><strong>Job:</strong> ' . \esc_html($jobId) . ' &nbsp; <strong>Status:</strong> <span id="dbsync-status-label">' . \esc_html($statusLabel) . '</span></p></div>';

        self::render_job_result_banner($outcomeData['outcome'], $outcomeData['message'], $isRunning);

        if ($exitCode !== null) {
            echo '<p id="dbsync-exit-wrap"><strong>Exit code:</strong> <span id="dbsync-exit-code">' . \esc_html((string) $exitCode) . '</span></p>';
        } else {
            echo '<p id="dbsync-exit-wrap" style="display:none;"><strong>Exit code:</strong> <span id="dbsync-exit-code"></span></p>';
        }

        $downloadUrlInit = self::export_download_url_if_ready($meta, $isRunning);
        echo '<p id="dbsync-download-wrap" class="dbsync-download-wrap" style="margin:12px 0;' . ($downloadUrlInit === null ? ' display:none;' : '') . '">';
        echo '<a id="dbsync-download-link" class="button button-primary" href="' . ($downloadUrlInit !== null ? \esc_url($downloadUrlInit) : '#') . '">Download SQL backup</a>';
        echo '</p>';

        if ($logContent !== '') {
            echo '<h3 style="margin-top:16px;">Log</h3>';
            echo '<pre id="dbsync-job-log" style="background:#f6f7f7;padding:12px;max-height:420px;overflow:auto;">' . \esc_html($logContent) . '</pre>';
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
.dbsync-progress-inner.dbsync-progress-determinate { animation: none; margin-left: 0; min-width: 0; }
@keyframes dbsync-progress { 0% { margin-left: 0; } 50% { margin-left: 70%; } 100% { margin-left: 0; } }
.dbsync-job-result { box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04); }
.dbsync-job-result.notice-success { border-left-color: #00a32a; }
.dbsync-job-result.notice-error { border-left-color: #d63638; }
.dbsync-job-result.notice-warning { border-left-color: #dba617; }
.dbsync-job-result__text { margin: 0.65em 0; font-size: 14px; line-height: 1.5; }
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
    var innerBar = document.getElementById('dbsync-progress-inner');
    var pctLabel = document.getElementById('dbsync-media-percent-label');
    var actionType = panel.getAttribute('data-action-type') || 'dbsync';

    function applyJobOutcome(data) {
        var resultEl = document.getElementById('dbsync-job-result');
        var resultText = document.getElementById('dbsync-job-result-text');
        if (!resultEl || !resultText) return;
        if (data.running) {
            resultEl.style.display = 'none';
            return;
        }
        resultEl.className = 'dbsync-job-result notice';
        var o = data.job_outcome || 'unknown';
        if (o === 'success') {
            resultEl.classList.add('notice-success');
        } else if (o === 'failure') {
            resultEl.classList.add('notice-error');
        } else {
            resultEl.classList.add('notice-warning');
        }
        resultText.textContent = data.job_outcome_message || '';
        resultEl.style.display = '';
    }

    if (!running) return;

    function applyMediaPercent(pct) {
        if (actionType !== 'media-sync' || pct === null || pct === undefined) {
            if (innerBar) {
                innerBar.classList.remove('dbsync-progress-determinate');
                innerBar.style.width = '';
            }
            if (pctLabel) pctLabel.style.display = 'none';
            return;
        }
        if (pctLabel) {
            pctLabel.style.display = '';
            pctLabel.textContent = pct + '%';
        }
        if (innerBar) {
            innerBar.classList.add('dbsync-progress-determinate');
            innerBar.style.width = Math.min(100, Math.max(0, parseInt(pct, 10))) + '%';
        }
    }

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
                if (actionType === 'media-sync' && data.media_percent !== null && data.media_percent !== undefined) {
                    var pctNum = parseInt(String(data.media_percent), 10);
                    if (!isNaN(pctNum)) {
                        applyMediaPercent(pctNum);
                    }
                }
                if (logEl && typeof data.log === 'string') {
                    logEl.textContent = data.log;
                    logEl.scrollTop = logEl.scrollHeight;
                }
                var downloadWrap = document.getElementById('dbsync-download-wrap');
                var downloadLink = document.getElementById('dbsync-download-link');
                if (data.download_url) {
                    if (downloadWrap) downloadWrap.style.display = '';
                    if (downloadLink) downloadLink.href = data.download_url;
                }
                applyJobOutcome(data);
                if (!data.running) {
                    clearInterval(interval);
                    applyMediaPercent(null);
                }
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
     * Derive success / failure for UI when meta exit_code is missing (common for background WP-CLI jobs).
     *
     * @param mixed $exitCode
     *
     * @return array{outcome: string, message: string}
     */
    private static function compute_job_outcome($exitCode, bool $running, string $log): array
    {
        if ($running) {
            return ['outcome' => 'running', 'message' => ''];
        }
        if ($exitCode !== null && $exitCode !== '') {
            $code = (int) $exitCode;
            if ($code === 0) {
                return ['outcome' => 'success', 'message' => 'Job completed successfully.'];
            }

            return ['outcome' => 'failure', 'message' => 'Job failed (exit code ' . $code . ').'];
        }
        if ($log === '') {
            return ['outcome' => 'unknown', 'message' => 'Job finished; no log output was recorded.'];
        }
        $tail = \strlen($log) > 20000 ? \substr($log, -20000) : $log;
        if (\stripos($tail, 'PHP Fatal error') !== false
            || \preg_match('/\bFatal error:\b/i', $tail) === 1) {
            return ['outcome' => 'failure', 'message' => 'Job failed (fatal error in log).'];
        }
        $lines = \preg_split('/\R/', $log, -1, PREG_SPLIT_NO_EMPTY);
        if (!\is_array($lines)) {
            $lines = [];
        }
        for ($i = \count($lines) - 1; $i >= 0; $i--) {
            $line = \trim($lines[$i]);
            if ($line === '') {
                continue;
            }
            if (\str_starts_with($line, 'Error:')) {
                $rest = \trim(\substr($line, \strlen('Error:')));

                return [
                    'outcome' => 'failure',
                    'message' => $rest !== '' ? 'Job failed: ' . $rest : 'Job failed.',
                ];
            }
            if (\str_starts_with($line, 'Success:')) {
                $rest = \trim(\substr($line, \strlen('Success:')));

                return [
                    'outcome' => 'success',
                    'message' => $rest !== '' ? $rest : 'Job completed successfully.',
                ];
            }
        }

        return ['outcome' => 'unknown', 'message' => 'Job finished; check the log to confirm the result.'];
    }

    private static function render_job_result_banner(string $outcome, string $message, bool $isRunning): void
    {
        if ($isRunning) {
            echo '<div id="dbsync-job-result" class="dbsync-job-result notice" style="display:none;margin-top:12px;" role="status" aria-live="polite"><p id="dbsync-job-result-text" class="dbsync-job-result__text"></p></div>';

            return;
        }
        $classes = 'dbsync-job-result notice';
        if ($outcome === 'success') {
            $classes .= ' notice-success';
        } elseif ($outcome === 'failure') {
            $classes .= ' notice-error';
        } else {
            $classes .= ' notice-warning';
        }
        echo '<div id="dbsync-job-result" class="' . \esc_attr($classes) . '" style="margin-top:12px;" role="status" aria-live="polite"><p id="dbsync-job-result-text" class="dbsync-job-result__text">' . \esc_html($message) . '</p></div>';
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
        $decoded = [];

        if (\file_exists($metaPath)) {
            $raw = \file_get_contents($metaPath);
            $decoded = \json_decode((string) $raw, true);
            if (!\is_array($decoded)) {
                $decoded = [];
            }
            if ($decoded !== []) {
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

        $mediaPercent = null;
        if (isset($decoded['action_type']) && (string) $decoded['action_type'] === 'media-sync' && $log !== '') {
            $mediaPercent = self::parse_media_rsync_percent($log);
        }

        $downloadUrl = self::export_download_url_if_ready($decoded, $running);

        $outcome = self::compute_job_outcome($exitCode, $running, $log);

        return [
            'running' => $running,
            'exit_code' => $exitCode,
            'log' => $log,
            'media_percent' => $mediaPercent,
            'download_url' => $downloadUrl,
            'job_outcome' => $outcome['outcome'],
            'job_outcome_message' => $outcome['message'],
        ];
    }

    /**
     * Signed admin URL to download a finished local export, or null if not available.
     *
     * @param array<string, mixed> $meta
     */
    private static function export_download_url_if_ready(array $meta, bool $running): ?string
    {
        if ($running) {
            return null;
        }
        if (!isset($meta['action_type']) || (string) $meta['action_type'] !== 'db-export') {
            return null;
        }
        if (($meta['run_mode'] ?? '') !== 'run') {
            return null;
        }
        $basename = isset($meta['export_final_basename']) ? (string) $meta['export_final_basename'] : '';
        if ($basename === '' || !self::is_safe_export_basename($basename)) {
            return null;
        }
        $path = self::get_exports_dir() . DIRECTORY_SEPARATOR . $basename;
        if (!\is_readable($path)) {
            return null;
        }
        $size = \filesize($path);
        if ($size === false || $size < 1) {
            return null;
        }

        return \admin_url(
            'admin-post.php?action=dbsync_download_export&file=' . \rawurlencode($basename)
            . '&_wpnonce=' . \wp_create_nonce('dbsync_download_export')
        );
    }

    private static function get_exports_dir(): string
    {
        $base = defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : \ABSPATH . 'wp-content';

        return $base . DIRECTORY_SEPARATOR . 'dbsync-exports';
    }

    private static function is_safe_export_basename(string $name): bool
    {
        return \preg_match('/^dbsync-local-\d{8}-\d{6}\.sql(\.gz)?$/', $name) === 1;
    }

    /**
     * Last known rsync transfer percentage from log (0–100), for media sync UI.
     */
    private static function parse_media_rsync_percent(string $log): ?int
    {
        if (\preg_match_all('/\[dbsync\] rsync_percent:\s*(\d+)/', $log, $m)) {
            $p = (int) \end($m[1]);
            return $p >= 0 && $p <= 100 ? $p : null;
        }
        if (\preg_match_all('/\b(\d{1,3})%\s/', $log, $m2)) {
            $p = (int) \end($m2[1]);
            return $p >= 0 && $p <= 100 ? $p : null;
        }

        return null;
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

