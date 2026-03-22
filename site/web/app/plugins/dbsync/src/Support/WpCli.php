<?php

namespace BrandonRP\DBSync\Support;

use BrandonRP\DBSync\Util\WpCli as UtilWpCli;

/**
 * Backwards-compatible alias for the planned file layout in the plan.
 */
final class WpCli
{
    public static function run(string $command, array $options = []): string
    {
        return UtilWpCli::run($command, $options);
    }
}

