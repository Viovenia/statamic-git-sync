<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Contracts;

use Viovenia\GitSync\DataTransferObjects\Sync\SyncContextDTO;
use Viovenia\GitSync\DataTransferObjects\Sync\SyncResultDTO;

/**
 * The single entry point, and what the GitSync facade points at.
 *
 * Never throws: every failure comes back as a SyncResultDTO carrying a
 * FailureDTO, so callers decide what a failure means for them. The queue job
 * uses `retryable()` to decide whether releasing the job is worth anything.
 */
interface SyncManagerContract
{
    public function sync(SyncContextDTO $context): SyncResultDTO;
}
