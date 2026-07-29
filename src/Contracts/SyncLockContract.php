<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Contracts;

use Closure;
use Viovenia\GitSync\Exceptions\LockUnavailableException;

interface SyncLockContract
{
    /**
     * Run the callback while holding the sync lock.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     *
     * @throws LockUnavailableException when another run holds the lock
     */
    public function run(Closure $callback): mixed;
}
