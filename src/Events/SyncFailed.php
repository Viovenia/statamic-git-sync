<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Events;

use Viovenia\GitSync\DataTransferObjects\Sync\SyncContextDTO;
use Viovenia\GitSync\DataTransferObjects\Sync\SyncResultDTO;

/** A run failed. `$result->failure` says what happened and whether a retry is worth anything. */
final class SyncFailed
{
    public function __construct(
        public readonly SyncContextDTO $context,
        public readonly SyncResultDTO $result,
    ) {}
}
