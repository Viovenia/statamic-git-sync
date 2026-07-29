<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Events;

use Viovenia\GitSync\DataTransferObjects\PullRequest\PullRequestDTO;
use Viovenia\GitSync\DataTransferObjects\Sync\SyncContextDTO;

/**
 * A pull request was opened for content changes.
 *
 * Fires only when one was actually created — a run that appended commits to
 * an already open pull request stays quiet, because nobody wants the same
 * notification every debounce window.
 */
final class PullRequestOpened
{
    public function __construct(
        public readonly SyncContextDTO $context,
        public readonly PullRequestDTO $pullRequest,
    ) {}
}
