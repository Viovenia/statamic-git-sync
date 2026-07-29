<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Services\Diagnostics\Checks;

use Viovenia\GitSync\Contracts\GitRepositoryContract;
use Viovenia\GitSync\Contracts\GitRepositoryLocatorContract;
use Viovenia\GitSync\Contracts\HealthCheckContract;
use Viovenia\GitSync\DataTransferObjects\Config\PushConfigDTO;
use Viovenia\GitSync\DataTransferObjects\Diagnostics\HealthCheckResultDTO;

final class RemoteCheck implements HealthCheckContract
{
    public function __construct(
        private readonly GitRepositoryContract $repository,
        private readonly GitRepositoryLocatorContract $locator,
        private readonly PushConfigDTO $config,
    ) {}

    public function id(): string
    {
        return 'remote';
    }

    public function title(): string
    {
        return 'Remote';
    }

    public function run(): HealthCheckResultDTO
    {
        if (! $this->config->enabled) {
            return HealthCheckResultDTO::ok($this->id(), $this->title(), 'Pushing is disabled.');
        }

        if (! $this->locator->exists()) {
            return HealthCheckResultDTO::warning(
                $this->id(),
                $this->title(),
                'Skipped: there is no repository to inspect.',
                'Fix the repository check first.',
            );
        }

        if (! $this->repository->hasRemote($this->config->remote)) {
            return HealthCheckResultDTO::error(
                $this->id(),
                $this->title(),
                sprintf('No remote named "%s".', $this->config->remote),
                sprintf('Add it with `git remote add %s <url>`, or disable pushing.', $this->config->remote),
            );
        }

        return HealthCheckResultDTO::ok($this->id(), $this->title(), $this->config->remote.' exists');
    }
}
