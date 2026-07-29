<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Events;

use Viovenia\GitSync\DataTransferObjects\Sync\SyncContextDTO;
use Viovenia\GitSync\DataTransferObjects\Sync\SyncResultDTO;

/** A run decided not to do anything: disabled, dry run, or another run held the lock. */
final class SyncSkipped
{
    public function __construct(
        public readonly SyncContextDTO $context,
        public readonly SyncResultDTO $result,
    ) {}
}
