<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Exceptions;

use Throwable;
use Viovenia\GitSync\Enums\FailureType;

final class GitBinaryNotFoundException extends GitSyncException
{
    public static function forBinary(string $binary, ?Throwable $previous = null): self
    {
        return new self(sprintf(
            'The git binary "%s" could not be executed. Set [statamic-git-sync.repository.git_binary] '
            .'to an absolute path if git is not on the PATH of the user running PHP.',
            $binary,
        ), 0, $previous);
    }

    public function failureType(): FailureType
    {
        return FailureType::GitBinaryMissing;
    }
}
