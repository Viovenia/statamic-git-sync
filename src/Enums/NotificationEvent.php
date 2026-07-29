<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Enums;

/**
 * The events worth telling somebody about.
 *
 * Deliberately not every event the addon fires: nobody wants a message each
 * time a sync started, and a notification stream that is mostly noise gets
 * muted, which costs you the one message that mattered.
 */
enum NotificationEvent: string
{
    case SyncFailed = 'sync_failed';
    case ConflictDetected = 'conflict_detected';
    case PullRequestOpened = 'pull_request_opened';

    /** Every successful commit. Off by default — on a busy site this is a lot. */
    case SyncCompleted = 'sync_completed';
}
