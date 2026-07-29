<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Concerns;

/**
 * Strips credentials out of anything that might end up in a log line,
 * an exception message or a console table.
 *
 * Remote URLs of the form https://user:token@host/repo.git are the common
 * case: git echoes them back in error output, and a token in a log file is
 * a token in a backup, in a bug report, and in a screenshot.
 */
trait RedactsCredentials
{
    protected function redact(string $value): string
    {
        // https://user:token@host  ->  https://user:***@host
        $value = (string) preg_replace(
            '#(?<scheme>[a-z][a-z0-9+.-]*://)(?<user>[^:/@\s]+):(?<secret>[^@/\s]+)@#i',
            '$1$2:***@',
            $value,
        );

        // ssh://token@host and git@host stay as they are: no secret in there.
        // Anything that looks like a bearer style token gets masked too.
        return (string) preg_replace(
            '#\b(gh[pousr]_[A-Za-z0-9]{16,}|glpat-[A-Za-z0-9_-]{16,})\b#',
            '***',
            $value,
        );
    }
}
