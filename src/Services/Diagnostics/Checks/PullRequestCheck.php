<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Services\Diagnostics\Checks;

use Throwable;
use Viovenia\GitSync\Contracts\GitRepositoryContract;
use Viovenia\GitSync\Contracts\GitRepositoryLocatorContract;
use Viovenia\GitSync\Contracts\HealthCheckContract;
use Viovenia\GitSync\DataTransferObjects\Config\GitSyncConfigDTO;
use Viovenia\GitSync\DataTransferObjects\Diagnostics\HealthCheckResultDTO;
use Viovenia\GitSync\DataTransferObjects\Git\RepositorySlugDTO;
use Viovenia\GitSync\Strategies\PullRequestSyncStrategy;

/**
 * Everything the pull request mode needs before it can work at all.
 *
 * The branch check matters most: proposing a branch to itself is the mistake
 * people make on their first afternoon with this mode, and without this check
 * they would only find out when the forge rejects the request.
 */
final class PullRequestCheck implements HealthCheckContract
{
    public function __construct(
        private readonly GitSyncConfigDTO $config,
        private readonly GitRepositoryContract $repository,
        private readonly GitRepositoryLocatorContract $locator,
    ) {}

    public function id(): string
    {
        return 'pull-request';
    }

    public function title(): string
    {
        return 'Pull request mode';
    }

    public function run(): HealthCheckResultDTO
    {
        if ($this->config->strategy !== PullRequestSyncStrategy::IDENTIFIER) {
            return HealthCheckResultDTO::ok($this->id(), $this->title(), 'Not in use.');
        }

        $config = $this->config->pullRequest;

        if ($config->token === null || $config->token === '') {
            return HealthCheckResultDTO::error(
                $this->id(),
                $this->title(),
                'No API token configured, so no pull request can be opened.',
                'Set STATAMIC_GIT_SYNC_PR_TOKEN to a token with pull request write access.',
            );
        }

        if (! $this->config->push->enabled) {
            return HealthCheckResultDTO::error(
                $this->id(),
                $this->title(),
                'Pushing is disabled, so the branch would never reach the forge.',
                'Set STATAMIC_GIT_SYNC_PUSH=true — pull request mode needs it.',
            );
        }

        if (! $this->locator->exists()) {
            return HealthCheckResultDTO::warning(
                $this->id(),
                $this->title(),
                'Skipped: there is no repository to inspect.',
                'Fix the repository check first.',
            );
        }

        $slug = $this->slug();

        if ($slug === null) {
            return HealthCheckResultDTO::error(
                $this->id(),
                $this->title(),
                sprintf('Could not work out owner/name from remote "%s".', $this->config->push->remote),
                'Set [statamic-git-sync.pull_request.repository] to "owner/name".',
            );
        }

        try {
            $head = $this->repository->currentBranch();
        } catch (Throwable $exception) {
            return HealthCheckResultDTO::error(
                $this->id(),
                $this->title(),
                $exception->getMessage(),
                'Check the repository state with `git status`.',
            );
        }

        if ($head->value === $config->base->value) {
            return HealthCheckResultDTO::error(
                $this->id(),
                $this->title(),
                sprintf('This instance is on "%s", which is also the base branch.', $head->value),
                'Pull request mode never checks out another branch. Put the authoring instance on its own '
                .'branch, or point [statamic-git-sync.pull_request.base] at a different one.',
            );
        }

        return HealthCheckResultDTO::ok(
            $this->id(),
            $this->title(),
            sprintf('%s: %s → %s', $slug->toString(), $head->value, $config->base->value),
        );
    }

    private function slug(): ?RepositorySlugDTO
    {
        if ($this->config->pullRequest->repository !== null) {
            return $this->config->pullRequest->repository;
        }

        $url = $this->repository->remoteUrl($this->config->push->remote);

        return $url !== null ? RepositorySlugDTO::fromRemoteUrl($url) : null;
    }
}
