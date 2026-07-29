<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Services\Diagnostics\Checks;

use Viovenia\GitSync\Contracts\GitRepositoryLocatorContract;
use Viovenia\GitSync\Contracts\HealthCheckContract;
use Viovenia\GitSync\DataTransferObjects\Diagnostics\HealthCheckResultDTO;

final class RepositoryCheck implements HealthCheckContract
{
    public function __construct(private readonly GitRepositoryLocatorContract $locator) {}

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
                'Run `git init` there, or point [statamic-git-sync.repository.path] at the repository root.',
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

        return HealthCheckResultDTO::ok($this->id(), $this->title(), $this->locator->path());
    }
}
