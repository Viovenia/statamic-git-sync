<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Subscribers;

use Illuminate\Contracts\Events\Dispatcher;
use Viovenia\GitSync\Contracts\CommitAuthorResolverContract;
use Viovenia\GitSync\Contracts\SyncDispatcherContract;
use Viovenia\GitSync\DataTransferObjects\Config\GitSyncConfigDTO;
use Viovenia\GitSync\DataTransferObjects\Sync\SyncContextDTO;

/**
 * Listens to Statamic's content events and does exactly one thing: queue a run.
 *
 * No git, no filesystem, no exception handling — because there is nothing
 * here that can fail. That is deliberate: this code runs inside the request
 * that saved an entry, and a broken git setup must never stop somebody from
 * saving their work.
 *
 * The commit author is resolved here rather than in the worker, because this
 * is the only moment where there is an authenticated user to resolve.
 */
final class ContentEventSubscriber
{
    public function __construct(
        private readonly SyncDispatcherContract $sync,
        private readonly CommitAuthorResolverContract $authors,
        private readonly GitSyncConfigDTO $config,
    ) {}

    public function subscribe(Dispatcher $events): void
    {
        if (! $this->config->enabled) {
            return;
        }

        foreach ($this->config->events->subscribable() as $event) {
            $events->listen($event->eventClass(), [self::class, 'handle']);
        }
    }

    /**
     * The event payload is deliberately ignored.
     *
     * Which entry changed does not matter: the run reads the working tree
     * when it gets there, which is both simpler and more correct than trying
     * to map an event onto the files it touched.
     */
    public function handle(): void
    {
        $this->sync->dispatch(SyncContextDTO::fromContentEvent(
            $this->authors->resolve(),
            time(),
        ));
    }
}
