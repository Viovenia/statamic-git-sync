<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Services\Diagnostics\Checks;

use Viovenia\GitSync\Contracts\GitRepositoryLocatorContract;
use Viovenia\GitSync\Contracts\HealthCheckContract;
use Viovenia\GitSync\DataTransferObjects\Config\ContentConfigDTO;
use Viovenia\GitSync\DataTransferObjects\Diagnostics\HealthCheckResultDTO;

/**
 * A path in the allow list that does not exist is almost always a typo, and
 * a typo here means content silently never gets committed.
 */
final class ContentPathsCheck implements HealthCheckContract
{
    public function __construct(
        private readonly ContentConfigDTO $config,
        private readonly GitRepositoryLocatorContract $locator,
    ) {}

    public function id(): string
    {
        return 'content-paths';
    }

    public function title(): string
    {
        return 'Content paths';
    }

    public function run(): HealthCheckResultDTO
    {
        $missing = [];

        foreach ($this->config->paths->all() as $path) {
            if (! file_exists($this->locator->path().DIRECTORY_SEPARATOR.$path)) {
                $missing[] = $path;
            }
        }

        if ($missing === []) {
            return HealthCheckResultDTO::ok(
                $this->id(),
                $this->title(),
                implode(', ', $this->config->paths->all()),
            );
        }

        return HealthCheckResultDTO::warning(
            $this->id(),
            $this->title(),
            'Configured but missing: '.implode(', ', $missing),
            'Remove them from [statamic-git-sync.content.paths], or check the paths for typos.',
        );
    }
}
