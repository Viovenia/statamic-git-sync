<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Services\Diagnostics\Checks;

use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Viovenia\GitSync\Contracts\HealthCheckContract;
use Viovenia\GitSync\DataTransferObjects\Diagnostics\HealthCheckResultDTO;

/**
 * Debouncing and the sync lock both need a cache store that can hold an
 * atomic lock. Without one the addon still commits — just more often, and
 * without protection against two runs overlapping.
 */
final class CacheLockSupportCheck implements HealthCheckContract
{
    public function __construct(private readonly CacheRepository $cache) {}

    public function id(): string
    {
        return 'cache-locks';
    }

    public function title(): string
    {
        return 'Cache locks';
    }

    public function run(): HealthCheckResultDTO
    {
        $store = $this->cache->getStore();
        $name = $store::class;

        if ($store instanceof LockProvider) {
            return HealthCheckResultDTO::ok($this->id(), $this->title(), 'Supported by '.$name);
        }

        return HealthCheckResultDTO::warning(
            $this->id(),
            $this->title(),
            $name.' cannot hold atomic locks, so debouncing is disabled and runs are not serialised.',
            'Switch CACHE_STORE to redis, memcached, database or file.',
        );
    }
}
