<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Concerns;

use Psr\Log\LoggerInterface;
use Viovenia\GitSync\DataTransferObjects\Sync\SyncContextDTO;
use Viovenia\GitSync\DataTransferObjects\Sync\SyncResultDTO;

/**
 * One log line per run, at the configured level, with the context that makes
 * it useful three weeks later.
 *
 * Expects the using class to expose a LoggerInterface and the configured
 * level — SyncManager provides both as promoted constructor properties.
 */
trait WritesSyncLog
{
    abstract protected function logger(): LoggerInterface;

    abstract protected function logLevel(): string;

    protected function logResult(SyncContextDTO $context, SyncResultDTO $result): void
    {
        $payload = [...$context->toLogContext(), ...$result->toLogContext()];

        if ($result->failure !== null) {
            $this->logger()->error('[statamic-git-sync] sync failed', [
                ...$payload,
                ...$result->failure->toLogContext(),
            ]);

            return;
        }

        $this->logger()->log($this->logLevel(), '[statamic-git-sync] sync '.$result->outcome->value, $payload);
    }

    protected function logDebug(string $message, SyncContextDTO $context): void
    {
        $this->logger()->debug('[statamic-git-sync] '.$message, $context->toLogContext());
    }
}
