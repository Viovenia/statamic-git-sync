<?php

declare(strict_types=1);

namespace Viovenia\GitSync\DataTransferObjects\Sync;

use Viovenia\GitSync\DataTransferObjects\Config\GitSyncConfigDTO;

/**
 * The handful of settings the queue job needs before the container is available.
 *
 * Laravel calls `middleware()` directly rather than through the container, so
 * a job cannot ask for its dependencies there. Rather than reaching for a
 * facade or a config() call, the dispatcher takes a snapshot of what the
 * middleware needs and travels with the job.
 *
 * All scalars — this serialises into the queue payload without surprises.
 */
final readonly class SyncJobOptionsDTO
{
    public function __construct(
        public bool $enabled,
        public int $overlapExpiresAfter,
        public int $tries,
        public int $throttleAttempts,
        public int $throttleSeconds,
    ) {}

    public static function fromConfig(GitSyncConfigDTO $config): self
    {
        return new self(
            enabled: $config->enabled,
            // Generous on purpose: a lock released while git is still running
            // would let a second run in, which is the one thing to avoid.
            overlapExpiresAfter: max(60, $config->repository->timeout * 3),
            tries: $config->queue->tries,
            throttleAttempts: 5,
            throttleSeconds: 10 * 60,
        );
    }
}
