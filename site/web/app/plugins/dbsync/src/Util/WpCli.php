<?php

namespace BrandonRP\DBSync\Util;

use WP_CLI;

final class WpCli
{
    /**
     * Run a WP-CLI command and return its trimmed stdout.
     *
     * @param string $command Full command string, excluding the leading `wp`.
     * @param array $options WP_CLI::runcommand options.
     */
    public static function run(string $command, array $options = []): string
    {
        $options = array_merge(
            [
                'return' => 'stdout',
                'exit_error' => true,
                'launch' => true,
            ],
            $options
        );

        /** @var string $out */
        $out = WP_CLI::runcommand($command, $options);
        return trim((string) $out);
    }
}

