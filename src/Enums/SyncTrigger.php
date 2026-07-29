<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Enums;

enum SyncTrigger: string
{
    /** A content change in the control panel (or via the API). */
    case ContentEvent = 'content_event';

    /** Somebody ran the artisan command by hand. */
    case Console = 'console';

    /** The scheduler ran the command on its cron expression. */
    case Schedule = 'schedule';

    /** The safety net caught changes that the debounced job never picked up. */
    case SafetyNet = 'safety_net';

    /**
     * Whether this trigger is allowed to wait for the debounce window.
     *
     * Only content events are debounced. Everything else was asked for
     * explicitly and runs straight away.
     */
    public function debounceable(): bool
    {
        return $this === self::ContentEvent;
    }
}
