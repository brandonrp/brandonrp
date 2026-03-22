<?php
/**
 * Plugin Name: DBSync
 * Description: Sync WordPress database (and optionally media) between environments using WP-CLI.
 * Version: 0.1.0
 * Author: BrandonRP
 * License: GPLv2 or later
 */

namespace BrandonRP\DBSync;

// This plugin provides both WP-CLI commands and an optional WP admin UI.

if (defined('WP_CLI') && WP_CLI) {
    require_once __DIR__ . '/src/Util/WpCli.php';

    require_once __DIR__ . '/src/Command/BaseCommand.php';
    require_once __DIR__ . '/src/Command/DbSyncFlow.php';
    require_once __DIR__ . '/src/Command/Export.php';
    require_once __DIR__ . '/src/Command/Import.php';
    require_once __DIR__ . '/src/Command/Sync.php';
    require_once __DIR__ . '/src/Command/MediaSync.php';
    require_once __DIR__ . '/src/Command/Status.php';

    require_once __DIR__ . '/src/Support/RemoteTransfer.php';
    require_once __DIR__ . '/src/Support/UrlReplace.php';

    require_once __DIR__ . '/src/Command/DbSyncCommand.php';

    // Register the top-level WP-CLI command: `wp dbsync ...`.
    \WP_CLI::add_command('dbsync', __NAMESPACE__ . '\\Command\\DbSyncCommand');
    return;
}

require_once __DIR__ . '/src/Admin/AdminHooks.php';
require_once __DIR__ . '/src/Admin/AdminPage.php';

Admin\AdminHooks::init();

