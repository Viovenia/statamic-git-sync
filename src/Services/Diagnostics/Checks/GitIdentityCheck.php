<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Services\Diagnostics\Checks;

use Viovenia\GitSync\Contracts\GitRepositoryContract;
use Viovenia\GitSync\Contracts\GitRepositoryLocatorContract;
use Viovenia\GitSync\Contracts\HealthCheckContract;
use Viovenia\GitSync\DataTransferObjects\Config\CommitConfigDTO;
use Viovenia\GitSync\DataTransferObjects\Diagnostics\HealthCheckResultDTO;

/**
 * git refuses to commit without a user.name and user.email. A configured
 * fallback author covers it, because that is passed as --author — but only
 * if git itself also has an identity for the committer field.
 */
final class GitIdentityCheck implements HealthCheckContract
{
    public function __construct(
        private readonly GitRepositoryContract $repository,
        private readonly GitRepositoryLocatorContract $locator,
        private readonly CommitConfigDTO $config,
    ) {}

    public function id(): string
    {
        return 'git-identity';
    }

    public function title(): string
    {
        return 'Git identity';
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

        $identity = $this->repository->identity();

        if ($identity !== null) {
            return HealthCheckResultDTO::ok($this->id(), $this->title(), $identity->toGitIdentity());
        }

        return HealthCheckResultDTO::error(
            $this->id(),
            $this->title(),
            'git has no user.name / user.email configured for this repository.',
            sprintf(
                'Run `git config user.name "%s"` and `git config user.email "%s"` inside %s.',
                $this->config->fallbackAuthor->name,
                $this->config->fallbackAuthor->email,
                $this->locator->path(),
            ),
        );
    }
}
