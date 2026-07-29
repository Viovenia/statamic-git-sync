<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Exceptions;

use Viovenia\GitSync\Enums\FailureType;

final class RepositoryNotFoundException extends GitSyncException
{
    public static function atPath(string $path): self
    {
        return new self(sprintf(
            'No git repository found at "%s". Run `git init` there, or point '
            .'[statamic-git-sync.repository.path] at the repository root.',
            $path,
        ));
    }

    public static function notWritable(string $path): self
    {
        return new self(sprintf(
            'The repository at "%s" is not writable by the user running PHP.',
            $path,
        ));
    }

    public function failureType(): FailureType
    {
        return FailureType::RepositoryMissing;
    }
}
