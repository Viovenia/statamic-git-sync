<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Events;

use Viovenia\GitSync\DataTransferObjects\Sync\SyncContextDTO;
use Viovenia\GitSync\DataTransferObjects\Sync\SyncResultDTO;

/** A run finished successfully — either with a commit or with nothing to do. */
final class SyncCompleted
{
    public function __construct(
        public readonly SyncContextDTO $context,
        public readonly SyncResultDTO $result,
    ) {}
}
