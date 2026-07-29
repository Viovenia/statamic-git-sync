<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Repositories;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Viovenia\GitSync\Contracts\DebounceRepositoryContract;
use Viovenia\GitSync\DataTransferObjects\Config\DebounceConfigDTO;

/**
 * Remembers when content last changed, so a run scheduled earlier can step
 * aside for a later one and a whole editing session becomes a single commit.
 *
 * This is deliberately a timing hint and nothing more. If the cache is
 * flushed, no commit is lost — the working tree still holds the changes and
 * the scheduled safety net still finds them. The worst case is a delay, not
 * a disappearance.
 */
final class DebounceRepository implements DebounceRepositoryContract
{
    public const CACHE_KEY = 'statamic-git-sync:debounce';

    public function __construct(
        private readonly CacheRepository $cache,
        private readonly DebounceConfigDTO $config,
    ) {}

    public function touch(int $timestamp): void
    {
        // Kept an order of magnitude longer than the window: expiring the
        // marker mid-window would let a superseded run go ahead.
        $this->cache->put(self::CACHE_KEY, $timestamp, max(60, $this->config->seconds * 10));
    }

    public function latest(): ?int
    {
        $value = $this->cache->get(self::CACHE_KEY);

        return is_int($value) ? $value : null;
    }

    public function isStale(int $timestamp): bool
    {
        $latest = $this->latest();

        return $latest !== null && $latest > $timestamp;
    }

    public function forget(): void
    {
        $this->cache->forget(self::CACHE_KEY);
    }

    public function enabled(): bool
    {
        return $this->config->enabled;
    }
}
