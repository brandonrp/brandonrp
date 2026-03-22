<?php

namespace BrandonRP\DBSync\Command;

use WP_CLI;

abstract class BaseCommand
{
    protected function is_dry_run(array $assoc_args): bool
    {
        // Default to preview for safety.
        return !isset($assoc_args['run']);
    }

    protected function confirm_or_dry_run(array $assoc_args, string $message): void
    {
        if ($this->is_dry_run($assoc_args)) {
            WP_CLI::warning($message . ' (dry-run preview; add `--run` to execute)');
            return;
        }
    }

    protected function add_ssh_option(array $assoc_args, string $ssh, array &$wpArgs): void
    {
        if ($ssh !== '') {
            $wpArgs[] = '--ssh=' . $ssh;
        }
    }
}

