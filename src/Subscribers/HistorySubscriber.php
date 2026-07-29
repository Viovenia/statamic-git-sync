<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Subscribers;

use Illuminate\Contracts\Events\Dispatcher;
use Viovenia\GitSync\Contracts\HistoryRepositoryContract;
use Viovenia\GitSync\DataTransferObjects\History\SyncHistoryEntryDTO;
use Viovenia\GitSync\Events\SyncCompleted;
use Viovenia\GitSync\Events\SyncFailed;

/**
 * Records every run that did something, for the control panel widget.
 *
 * A listener rather than a call inside SyncManager, for the same reason as
 * the notifications: the events are the seam, and history is a display
 * concern that the sync path should not know about.
 */
final class HistorySubscriber
{
    public function __construct(private readonly HistoryRepositoryContract $history) {}

    public function subscribe(Dispatcher $events): void
    {
        if (! $this->history->enabled()) {
            return;
        }

        $events->listen(SyncCompleted::class, [self::class, 'handleCompleted']);
        $events->listen(SyncFailed::class, [self::class, 'handleFailed']);
    }

    public function handleCompleted(SyncCompleted $event): void
    {
        // A run that found nothing is not worth a line; on a quiet site the
        // safety net alone would fill the file with "nothing to commit".
        if ($event->result->changes->isEmpty()) {
            return;
        }

        $this->history->record(SyncHistoryEntryDTO::fromResult($event->context, $event->result));
    }

    public function handleFailed(SyncFailed $event): void
    {
        $this->history->record(SyncHistoryEntryDTO::fromResult($event->context, $event->result));
    }
}
