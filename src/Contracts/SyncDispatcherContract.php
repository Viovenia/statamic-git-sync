<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Contracts;

use Viovenia\GitSync\DataTransferObjects\Sync\SyncContextDTO;

/**
 * Turns "something changed" into a queued run, without doing any work
 * in the request that triggered it.
 */
interface SyncDispatcherContract
{
    public function dispatch(SyncContextDTO $context): void;
}
