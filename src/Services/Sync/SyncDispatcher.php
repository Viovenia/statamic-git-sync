<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Services\Sync;

use Illuminate\Contracts\Bus\Dispatcher as BusDispatcher;
use Viovenia\GitSync\Contracts\DebounceRepositoryContract;
use Viovenia\GitSync\Contracts\SyncDispatcherContract;
use Viovenia\GitSync\DataTransferObjects\Config\GitSyncConfigDTO;
use Viovenia\GitSync\DataTransferObjects\Sync\SyncContextDTO;
use Viovenia\GitSync\DataTransferObjects\Sync\SyncJobOptionsDTO;
use Viovenia\GitSync\Jobs\SyncContentJob;

/**
 * Turns "content changed" into a queued run.
 *
 * Two lines of work happen in the request that saved an entry: writing the
 * debounce marker and pushing a job onto the queue. Everything expensive —
 * and everything that can fail — happens in the worker, which is why a
 * broken git setup can never stop somebody from saving.
 */
final class SyncDispatcher implements SyncDispatcherContract
{
    public function __construct(
        private readonly BusDispatcher $bus,
        private readonly DebounceRepositoryContract $debounce,
        private readonly GitSyncConfigDTO $config,
    ) {}

    public function dispatch(SyncContextDTO $context): void
    {
        if (! $this->config->enabled) {
            return;
        }

        // Written before dispatching: a job that starts early must be able to
        // see that a newer change is on its way and step aside.
        $this->debounce->touch($context->requestedAt);

        $job = new SyncContentJob($context, SyncJobOptionsDTO::fromConfig($this->config));

        if ($this->config->queue->connection !== null) {
            $job->onConnection($this->config->queue->connection);
        }

        if ($this->config->queue->name !== null) {
            $job->onQueue($this->config->queue->name);
        }

        if ($context->trigger->debounceable() && $this->debounce->enabled()) {
            $job->delay($this->config->debounce->delaySeconds());
        }

        $this->bus->dispatch($job);
    }
}
