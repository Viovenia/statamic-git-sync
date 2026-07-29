<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Services\Diagnostics\Checks;

use Viovenia\GitSync\Contracts\HealthCheckContract;
use Viovenia\GitSync\DataTransferObjects\Config\HistoryConfigDTO;
use Viovenia\GitSync\DataTransferObjects\Diagnostics\HealthCheckResultDTO;
use Viovenia\GitSync\Repositories\FileHistoryRepository;

/**
 * An unwritable history file is a warning, never an error: syncing works
 * perfectly well without it, only the widget stays empty.
 */
final class HistoryCheck implements HealthCheckContract
{
    public function __construct(
        private readonly HistoryConfigDTO $config,
        private readonly FileHistoryRepository $repository,
    ) {}

    public function id(): string
    {
        return 'history';
    }

    public function title(): string
    {
        return 'History';
    }

    public function run(): HealthCheckResultDTO
    {
        if (! $this->config->enabled) {
            return HealthCheckResultDTO::ok($this->id(), $this->title(), 'Off.');
        }

        if (! $this->repository->writable()) {
            return HealthCheckResultDTO::warning(
                $this->id(),
                $this->title(),
                'Not writable, so the control panel widget will stay empty: '.$this->config->path,
                'Give the PHP user write access to that directory, or switch history off. Syncing is unaffected.',
            );
        }

        return HealthCheckResultDTO::ok(
            $this->id(),
            $this->title(),
            sprintf('%s (max %d entries)', $this->config->path, $this->config->maxEntries),
        );
    }
}
