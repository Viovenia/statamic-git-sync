<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Exceptions;

use Viovenia\GitSync\DataTransferObjects\Git\CommandResultDTO;
use Viovenia\GitSync\Enums\FailureType;
use Viovenia\GitSync\Enums\PullStrategy;

/**
 * Integrating remote work into the local branch conflicted.
 *
 * By the time this is thrown the rebase or merge has already been aborted,
 * so the working tree is back exactly where it was. Retrying changes
 * nothing — a human has to resolve the conflict.
 */
final class RebaseConflictException extends GitSyncException
{
    private function __construct(
        string $message,
        private readonly ?CommandResultDTO $result,
    ) {
        parent::__construct($message);
    }

    public static function during(PullStrategy $strategy, ?CommandResultDTO $result = null): self
    {
        return new self(sprintf(
            'Pulling with %s conflicted and was aborted. The working tree was left untouched. '
            .'Resolve the divergence by hand, then the next content change will sync again.',
            $strategy->value,
        ), $result);
    }

    public function commandResult(): ?CommandResultDTO
    {
        return $this->result;
    }

    public function failureType(): FailureType
    {
        return FailureType::RebaseConflict;
    }
}
