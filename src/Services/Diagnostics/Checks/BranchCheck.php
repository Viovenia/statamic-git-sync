<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Services\Diagnostics\Checks;

use Throwable;
use Viovenia\GitSync\Contracts\GitRepositoryContract;
use Viovenia\GitSync\Contracts\GitRepositoryLocatorContract;
use Viovenia\GitSync\Contracts\HealthCheckContract;
use Viovenia\GitSync\DataTransferObjects\Config\PushConfigDTO;
use Viovenia\GitSync\DataTransferObjects\Diagnostics\HealthCheckResultDTO;

final class BranchCheck implements HealthCheckContract
{
    public function __construct(
        private readonly GitRepositoryContract $repository,
        private readonly GitRepositoryLocatorContract $locator,
        private readonly PushConfigDTO $config,
    ) {}

    public function id(): string
    {
        return 'branch';
    }

    public function title(): string
    {
        return 'Branch';
    }

    public function run(): HealthCheckResultDTO
    {
        if (! $this->locator->exists()) {
            return HealthCheckResultDTO::warning(
                $this->id(),
                $this->title(),
                'Skipped: there is no repository to inspect.',
                'Fix the repository check first.',
            );
        }

        if (! $this->repository->hasCommits()) {
            return HealthCheckResultDTO::warning(
                $this->id(),
                $this->title(),
                'The repository has no commits yet, so there is no branch to push.',
                'Make an initial commit by hand — the first automated commit will work afterwards.',
            );
        }

        try {
            $branch = $this->repository->currentBranch();
        } catch (Throwable $exception) {
            return HealthCheckResultDTO::error(
                $this->id(),
                $this->title(),
                $exception->getMessage(),
                'Check the repository state with `git status`.',
            );
        }

        if ($branch->isDetached()) {
            return HealthCheckResultDTO::error(
                $this->id(),
                $this->title(),
                'HEAD is detached, so commits would not belong to any branch.',
                'Check out a branch with `git checkout <branch>`.',
            );
        }

        $configured = $this->config->branch;

        if ($configured !== null && $configured->value !== $branch->value) {
            return HealthCheckResultDTO::warning(
                $this->id(),
                $this->title(),
                sprintf('Configured to push "%s" while the repository is on "%s".', $configured->value, $branch->value),
                'Set [statamic-git-sync.push.branch] to null to always push the current branch.',
            );
        }

        return HealthCheckResultDTO::ok($this->id(), $this->title(), $branch->value);
    }
}
