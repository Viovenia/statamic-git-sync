<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Events;

use Viovenia\GitSync\DataTransferObjects\Sync\FailureDTO;
use Viovenia\GitSync\DataTransferObjects\Sync\SyncContextDTO;

/**
 * Integrating remote work conflicted and was rolled back.
 *
 * The working tree is untouched, so this needs a human rather than a retry.
 * Listen here if you want to be told about it somewhere other than the log.
 */
final class ConflictDetected
{
    public function __construct(
        public readonly SyncContextDTO $context,
        public readonly FailureDTO $failure,
    ) {}
}
