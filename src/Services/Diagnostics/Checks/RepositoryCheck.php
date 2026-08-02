<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Services\Diagnostics\Checks;

use Viovenia\GitSync\Contracts\GitRepositoryContract;
use Viovenia\GitSync\Contracts\GitRepositoryLocatorContract;
use Viovenia\GitSync\Contracts\HealthCheckContract;
use Viovenia\GitSync\DataTransferObjects\Diagnostics\HealthCheckResultDTO;

final class RepositoryCheck implements HealthCheckContract
{
    public function __construct(
        private readonly GitRepositoryLocatorContract $locator,
        private readonly GitRepositoryContract $repository,
    ) {}

    public function id(): string
    {
        return 'repository';
    }

    public function title(): string
    {
        return 'Repository';
    }

    public function run(): HealthCheckResultDTO
    {
        if (! $this->locator->exists()) {
            return HealthCheckResultDTO::error(
                $this->id(),
                $this->title(),
                'No git repository at '.$this->locator->path(),
                // The likeliest cause on a modern host is not a missing `git init`
                // but a platform that never had a checkout to begin with, so the
                // remedy names that case rather than leaving people to guess.
                'Run `git init` there, or point [statamic-git-sync.repository.path] at the repository root. '
                .'On Laravel Cloud, or in a container built from an immutable image, there is no repository '
                .'to commit into and the filesystem is discarded on every deploy — this addon needs a '
                .'persistent checkout. See docs/installation.md.',
            );
        }

        if (! $this->locator->writable()) {
            return HealthCheckResultDTO::error(
                $this->id(),
                $this->title(),
                'Not writable by the PHP user: '.$this->locator->path(),
                'Fix the ownership or permissions of the repository directory.',
            );
        }

        $pending = $this->repository->pendingOperation();

        if ($pending !== null) {
            return HealthCheckResultDTO::error(
                $this->id(),
                $this->title(),
                sprintf('A %s is half-finished here, so nothing will be committed until it is resolved.', $pending),
                sprintf('Run `git %s --continue` or `git %s --abort` in %s.', $pending, $pending, $this->locator->path()),
            );
        }

        return HealthCheckResultDTO::ok($this->id(), $this->title(), $this->locator->path());
    }
}
