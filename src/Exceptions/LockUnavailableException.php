<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Exceptions;

use Viovenia\GitSync\Enums\FailureType;

/**
 * Another sync run holds the lock.
 *
 * This is not an error. The run that holds the lock reads the working tree
 * when it gets there, so it picks up whatever this run would have committed.
 */
final class LockUnavailableException extends GitSyncException
{
    public static function forKey(string $key): self
    {
        return new self(sprintf('Another sync run holds the lock "%s".', $key));
    }

    public function failureType(): FailureType
    {
        return FailureType::LockUnavailable;
    }
}
