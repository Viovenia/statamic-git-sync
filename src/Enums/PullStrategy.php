<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Enums;

enum PullStrategy: string
{
    case Rebase = 'rebase';
    case Merge = 'merge';

    /** The git flag that selects this strategy on `git pull`. */
    public function flag(): string
    {
        return match ($this) {
            self::Rebase => '--rebase',
            self::Merge => '--no-rebase',
        };
    }

    /** The git subcommand that undoes a half-finished integration. */
    public function abortCommand(): string
    {
        return match ($this) {
            self::Rebase => 'rebase',
            self::Merge => 'merge',
        };
    }
}
