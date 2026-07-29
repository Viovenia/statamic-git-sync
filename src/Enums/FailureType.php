<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Enums;

enum FailureType: string
{
    case Configuration = 'configuration';
    case GitBinaryMissing = 'git_binary_missing';
    case RepositoryMissing = 'repository_missing';
    case CommandFailed = 'command_failed';
    case RebaseConflict = 'rebase_conflict';
    case LockUnavailable = 'lock_unavailable';
    case Unknown = 'unknown';

    /**
     * Whether trying the exact same run again could plausibly succeed.
     *
     * A missing git binary will still be missing in thirty seconds. A push
     * that lost the network might well go through.
     */
    public function retryable(): bool
    {
        return match ($this) {
            self::CommandFailed, self::Unknown => true,
            default => false,
        };
    }
}
