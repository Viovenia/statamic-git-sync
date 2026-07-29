<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Exceptions;

use Viovenia\GitSync\Enums\FailureType;

/**
 * The commit and the push succeeded; talking to the forge did not.
 *
 * Worth separating from a git failure: the work is safely pushed, so the only
 * thing missing is the pull request — and a retry costs nothing.
 */
final class PullRequestFailedException extends GitSyncException
{
    public static function requestFailed(string $action, int $status, string $body): self
    {
        return new self(sprintf(
            'The forge rejected the request to %s (HTTP %d): %s',
            $action,
            $status,
            mb_substr(trim($body), 0, 500),
        ));
    }

    public static function unreachable(string $action, string $reason): self
    {
        return new self(sprintf('Could not reach the forge to %s: %s', $action, $reason));
    }

    public static function unexpectedResponse(string $action): self
    {
        return new self(sprintf(
            'The forge answered the request to %s with something this addon could not read.',
            $action,
        ));
    }

    public function failureType(): FailureType
    {
        return FailureType::PullRequestFailed;
    }
}
