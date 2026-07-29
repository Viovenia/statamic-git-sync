<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Concerns;

use Illuminate\Contracts\Events\Dispatcher;
use Viovenia\GitSync\DataTransferObjects\Sync\SyncContextDTO;
use Viovenia\GitSync\DataTransferObjects\Sync\SyncResultDTO;
use Viovenia\GitSync\Enums\SyncOutcome;
use Viovenia\GitSync\Events\ConflictDetected;
use Viovenia\GitSync\Events\SyncCompleted;
use Viovenia\GitSync\Events\SyncFailed;
use Viovenia\GitSync\Events\SyncSkipped;
use Viovenia\GitSync\Events\SyncStarted;

/**
 * Keeps event dispatching in one place so every outcome fires exactly one
 * terminal event, and none of them is forgotten when a branch is added.
 */
trait FiresSyncEvents
{
    abstract protected function events(): Dispatcher;

    protected function fireStarted(SyncContextDTO $context): void
    {
        $this->events()->dispatch(new SyncStarted($context));
    }

    protected function fireOutcome(SyncContextDTO $context, SyncResultDTO $result): void
    {
        match ($result->outcome) {
            SyncOutcome::Committed,
            SyncOutcome::NothingToCommit => $this->events()->dispatch(new SyncCompleted($context, $result)),
            SyncOutcome::Skipped => $this->events()->dispatch(new SyncSkipped($context, $result)),
            SyncOutcome::Conflicted => $this->fireConflict($context, $result),
            SyncOutcome::Failed => $this->events()->dispatch(new SyncFailed($context, $result)),
        };
    }

    private function fireConflict(SyncContextDTO $context, SyncResultDTO $result): void
    {
        if ($result->failure !== null) {
            $this->events()->dispatch(new ConflictDetected($context, $result->failure));
        }

        $this->events()->dispatch(new SyncFailed($context, $result));
    }
}
