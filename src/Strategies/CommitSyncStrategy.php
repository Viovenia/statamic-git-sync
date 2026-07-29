<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Strategies;

use Viovenia\GitSync\Contracts\CommitMessageBuilderContract;
use Viovenia\GitSync\Contracts\GitRepositoryContract;
use Viovenia\GitSync\Contracts\GitRepositoryLocatorContract;
use Viovenia\GitSync\Contracts\PathFilterContract;
use Viovenia\GitSync\Contracts\SyncStrategyContract;
use Viovenia\GitSync\DataTransferObjects\Config\GitSyncConfigDTO;
use Viovenia\GitSync\DataTransferObjects\Git\BranchNameDTO;
use Viovenia\GitSync\DataTransferObjects\Git\PushResultDTO;
use Viovenia\GitSync\DataTransferObjects\Sync\DurationDTO;
use Viovenia\GitSync\DataTransferObjects\Sync\SyncContextDTO;
use Viovenia\GitSync\DataTransferObjects\Sync\SyncResultDTO;

/**
 * Commit locally, then optionally integrate and push.
 *
 * The sequence is deliberately boring: ask git what changed, stop if nothing
 * did, stage exactly the allowed paths, commit, and only then talk to the
 * remote. Nothing here catches exceptions — SyncManager owns the failure
 * policy, and a strategy that swallowed errors would make that impossible.
 */
final class CommitSyncStrategy implements SyncStrategyContract
{
    public const IDENTIFIER = 'commit';

    public function __construct(
        private readonly GitRepositoryContract $repository,
        private readonly GitRepositoryLocatorContract $locator,
        private readonly PathFilterContract $filter,
        private readonly CommitMessageBuilderContract $messages,
        private readonly GitSyncConfigDTO $config,
    ) {}

    public function identifier(): string
    {
        return self::IDENTIFIER;
    }

    public function execute(SyncContextDTO $context): SyncResultDTO
    {
        $start = microtime(true);

        $this->locator->ensureExists();

        $changes = $this->repository
            ->status($this->config->content->paths)
            ->filteredBy($this->filter);

        if ($changes->isEmpty() && ! $this->config->commit->allowEmpty) {
            return SyncResultDTO::nothingToCommit(DurationDTO::between($start, microtime(true)));
        }

        if ($context->dryRun || $this->config->dryRun) {
            return SyncResultDTO::skipped(
                sprintf('Dry run: %d file(s) would have been committed.', $changes->count()),
                DurationDTO::between($start, microtime(true)),
                $changes,
            );
        }

        $this->repository->stage($changes->paths());

        $commit = $this->repository->commit(
            $this->messages->build($changes, $context),
            $context->author,
            $this->config->commit->allowEmpty,
            $this->config->commit->sign,
        );

        $branch = $this->targetBranch();

        if ($this->config->pull->enabled) {
            $this->repository->pull(
                $this->config->pull->strategy,
                $this->config->push->remote,
                $branch,
            );
        }

        return SyncResultDTO::committed(
            $changes,
            $commit,
            $this->push($branch),
            DurationDTO::between($start, microtime(true)),
        );
    }

    private function push(BranchNameDTO $branch): ?PushResultDTO
    {
        if (! $this->config->push->enabled) {
            return null;
        }

        return $this->repository->push(
            $this->config->push->remote,
            $branch,
            $this->config->push->forceWithLease,
        );
    }

    private function targetBranch(): BranchNameDTO
    {
        return $this->config->push->branch ?? $this->repository->currentBranch();
    }
}
