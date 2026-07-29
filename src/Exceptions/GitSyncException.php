<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Exceptions;

use RuntimeException;
use Viovenia\GitSync\DataTransferObjects\Git\CommandResultDTO;
use Viovenia\GitSync\Enums\FailureType;

/**
 * Base class for everything this addon throws on purpose.
 *
 * Catching this one catches all of them, and every subclass answers the
 * two questions the caller actually has: what kind of failure was this,
 * and is trying again worth anything?
 */
abstract class GitSyncException extends RuntimeException
{
    abstract public function failureType(): FailureType;

    public function retryable(): bool
    {
        return $this->failureType()->retryable();
    }

    /** The git invocation that caused this, when there was one. */
    public function commandResult(): ?CommandResultDTO
    {
        return null;
    }
}
