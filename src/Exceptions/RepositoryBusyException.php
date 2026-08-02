<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Exceptions;

use Viovenia\GitSync\Enums\FailureType;

/**
 * The repository is in the middle of a merge, rebase or cherry-pick.
 *
 * Committing on top of that would bury somebody's unresolved conflict under
 * an automated commit — possibly with conflict markers in the content. So the
 * run stops and says so instead, and keeps saying so until a person finishes
 * what they started.
 */
final class RepositoryBusyException extends GitSyncException
{
    public static function during(string $operation, string $path): self
    {
        return new self(sprintf(
            'The repository at %s is in the middle of a %s. Nothing was committed: finishing it automatically '
            .'would bury the conflict. Resolve it there (`git %s --continue` or `git %s --abort`), and the next '
            .'content change will sync again.',
            $path,
            $operation,
            $operation,
            $operation,
        ));
    }

    public function failureType(): FailureType
    {
        return FailureType::RepositoryBusy;
    }
}
