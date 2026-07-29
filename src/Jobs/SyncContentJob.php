<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\Skip;
use Illuminate\Queue\Middleware\ThrottlesExceptions;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Viovenia\GitSync\Contracts\DebounceRepositoryContract;
use Viovenia\GitSync\Contracts\SyncManagerContract;
use Viovenia\GitSync\DataTransferObjects\Config\QueueConfigDTO;
use Viovenia\GitSync\DataTransferObjects\Sync\SyncContextDTO;
use Viovenia\GitSync\DataTransferObjects\Sync\SyncJobOptionsDTO;

/**
 * The queued half of a content change.
 *
 * Deliberately thin. Concurrency is Laravel's own job middleware, the
 * decision whether to run at all is the debounce marker, and the work itself
 * belongs to SyncManager. If a git command ever appears in this class,
 * something has gone wrong.
 */
final class SyncContentJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public const LOCK_KEY = 'statamic-git-sync:job';

    /** Retries are driven by hand in handle(), so the queue never retries on its own. */
    public int $tries = 1;

    public function __construct(
        public readonly SyncContextDTO $context,
        public readonly SyncJobOptionsDTO $options,
    ) {}

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [
            Skip::unless(fn (): bool => $this->options->enabled),

            // A discarded duplicate is harmless: the marker still stands, and
            // the run holding the lock reads the working tree anyway, so it
            // commits whatever this run would have.
            (new WithoutOverlapping(self::LOCK_KEY))
                ->dontRelease()
                ->expireAfter($this->options->overlapExpiresAfter),

            // A broken remote must not turn into thousands of failing jobs.
            new ThrottlesExceptions($this->options->throttleAttempts, $this->options->throttleSeconds),
        ];
    }

    public function handle(
        SyncManagerContract $manager,
        DebounceRepositoryContract $debounce,
        QueueConfigDTO $queue,
    ): void {
        // A newer change is already scheduled; that run picks up everything
        // this one would have committed.
        if ($debounce->isStale($this->context->requestedAt)) {
            return;
        }

        $result = $manager->sync($this->context);

        // Only network-shaped failures come back retryable. A missing binary
        // or a conflict will fail identically on the next attempt.
        if ($result->retryable() && $this->attempts() < $this->options->tries) {
            $this->release($queue->backoffFor($this->attempts()));
        }
    }

    public function displayName(): string
    {
        return 'statamic-git-sync:'.$this->context->trigger->value;
    }
}
