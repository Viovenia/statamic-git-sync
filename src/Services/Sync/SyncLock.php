<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Services\Sync;

use Closure;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Viovenia\GitSync\Contracts\SyncLockContract;
use Viovenia\GitSync\Exceptions\LockUnavailableException;

/**
 * Guarantees that two git sequences never run at the same time.
 *
 * The queue job already carries WithoutOverlapping middleware, but the
 * console command and the scheduler bypass the queue entirely. This lock is
 * what covers those paths, and what makes "two saves at once" safe on a
 * single-worker setup as well as a fleet.
 *
 * On a cache store without atomic locks the callback still runs — an
 * unguarded commit is better than no commit, and the doctor command reports
 * the store as a problem worth fixing.
 */
final class SyncLock implements SyncLockContract
{
    public const LOCK_KEY = 'statamic-git-sync:lock';

    public function __construct(
        private readonly CacheRepository $cache,
        private readonly int $secondsToHold,
    ) {}

    public function run(Closure $callback): mixed
    {
        $store = $this->cache->getStore();

        if (! $store instanceof LockProvider) {
            return $callback();
        }

        $lock = $store->lock(self::LOCK_KEY, $this->secondsToHold);

        if (! $lock->get()) {
            throw LockUnavailableException::forKey(self::LOCK_KEY);
        }

        try {
            return $callback();
        } finally {
            $lock->release();
        }
    }
}
