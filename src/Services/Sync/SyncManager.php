<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Services\Sync;

use Illuminate\Contracts\Events\Dispatcher;
use Psr\Log\LoggerInterface;
use Throwable;
use Viovenia\GitSync\Concerns\FiresSyncEvents;
use Viovenia\GitSync\Concerns\WritesSyncLog;
use Viovenia\GitSync\Contracts\SyncLockContract;
use Viovenia\GitSync\Contracts\SyncManagerContract;
use Viovenia\GitSync\Contracts\SyncStrategyResolverContract;
use Viovenia\GitSync\DataTransferObjects\Config\GitSyncConfigDTO;
use Viovenia\GitSync\DataTransferObjects\Sync\DurationDTO;
use Viovenia\GitSync\DataTransferObjects\Sync\FailureDTO;
use Viovenia\GitSync\DataTransferObjects\Sync\SyncContextDTO;
use Viovenia\GitSync\DataTransferObjects\Sync\SyncResultDTO;
use Viovenia\GitSync\Enums\ConflictBehaviour;
use Viovenia\GitSync\Exceptions\LockUnavailableException;
use Viovenia\GitSync\Exceptions\RebaseConflictException;

/**
 * The one entry point, and what the GitSync facade resolves to.
 *
 * It owns three things the strategies deliberately do not: the lock, the
 * events, and the failure policy. And it never throws — every outcome comes
 * back as a SyncResultDTO, so a caller in a web request cannot accidentally
 * break somebody's save, while the queue job can still read `retryable()`
 * and decide to release itself.
 *
 * The single exception is a conflict configured as `on_conflict => throw`,
 * where failing loudly is exactly what was asked for.
 */
final class SyncManager implements SyncManagerContract
{
    use FiresSyncEvents;
    use WritesSyncLog;

    public function __construct(
        private readonly SyncStrategyResolverContract $strategies,
        private readonly SyncLockContract $lock,
        private readonly Dispatcher $dispatcher,
        private readonly LoggerInterface $log,
        private readonly GitSyncConfigDTO $config,
    ) {}

    public function sync(SyncContextDTO $context): SyncResultDTO
    {
        $start = microtime(true);

        if (! $this->config->enabled) {
            return $this->finish($context, SyncResultDTO::skipped(
                'Disabled via [statamic-git-sync.enabled].',
                DurationDTO::between($start, microtime(true)),
            ));
        }

        $this->fireStarted($context);

        try {
            $result = $this->lock->run(
                fn (): SyncResultDTO => $this->strategies->resolve($this->config->strategy)->execute($context),
            );
        } catch (Throwable $exception) {
            // One catch, one policy. Anything the strategy throws travels back
            // up through the lock, so branching here keeps every decision about
            // what a failure means in a single readable place.
            return $this->handleFailure($context, $exception, $start);
        }

        return $this->finish($context, $result);
    }

    private function handleFailure(SyncContextDTO $context, Throwable $exception, float $start): SyncResultDTO
    {
        $duration = DurationDTO::between($start, microtime(true));

        // Not a failure: the run holding the lock reads the working tree when
        // it gets there, so it commits whatever this run would have.
        if ($exception instanceof LockUnavailableException) {
            return $this->finish($context, SyncResultDTO::skipped($exception->getMessage(), $duration));
        }

        if ($exception instanceof RebaseConflictException) {
            $result = $this->finish($context, SyncResultDTO::conflicted(
                FailureDTO::fromThrowable($exception),
                $duration,
            ));

            // Reported either way; rethrown only when failing loudly was asked for.
            if ($this->config->pull->onConflict === ConflictBehaviour::Throw) {
                throw $exception;
            }

            return $result;
        }

        return $this->finish($context, SyncResultDTO::failed(FailureDTO::fromThrowable($exception), $duration));
    }

    private function finish(SyncContextDTO $context, SyncResultDTO $result): SyncResultDTO
    {
        $this->fireOutcome($context, $result);
        $this->logResult($context, $result);

        return $result;
    }

    protected function events(): Dispatcher
    {
        return $this->dispatcher;
    }

    protected function logger(): LoggerInterface
    {
        return $this->log;
    }

    protected function logLevel(): string
    {
        return $this->config->logging->level;
    }
}
