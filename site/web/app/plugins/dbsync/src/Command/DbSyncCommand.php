<?php

namespace BrandonRP\DBSync\Command;

use WP_CLI;

/**
 * WP-CLI entry point: `wp dbsync <subcommand>`.
 */
class DbSyncCommand
{
    public function export($args, $assoc_args): void
    {
        $runner = new Export();
        $runner->run($args, $assoc_args);
    }

    public function import($args, $assoc_args): void
    {
        $runner = new Import();
        $runner->run($args, $assoc_args);
    }

    public function sync($args, $assoc_args): void
    {
        $runner = new Sync();
        $runner->run($args, $assoc_args);
    }

    public function media_sync($args, $assoc_args): void
    {
        $runner = new MediaSync();
        $runner->run($args, $assoc_args);
    }

    public function status($args, $assoc_args): void
    {
        $runner = new Status();
        $runner->run($args, $assoc_args);
    }

    public function compare($args, $assoc_args): void
    {
        $runner = new Compare();
        $runner->run($args, $assoc_args);
    }
}

