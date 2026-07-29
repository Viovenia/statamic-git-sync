<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Services\Diagnostics\Checks;

use Throwable;
use Viovenia\GitSync\Contracts\HealthCheckContract;
use Viovenia\GitSync\Contracts\ProcessRunnerContract;
use Viovenia\GitSync\DataTransferObjects\Diagnostics\HealthCheckResultDTO;
use Viovenia\GitSync\Services\Git\GitCommandBuilder;

final class GitBinaryCheck implements HealthCheckContract
{
    public function __construct(
        private readonly ProcessRunnerContract $processes,
        private readonly GitCommandBuilder $commands,
    ) {}

    public function id(): string
    {
        return 'git-binary';
    }

    public function title(): string
    {
        return 'Git binary';
    }

    public function run(): HealthCheckResultDTO
    {
        try {
            $result = $this->processes->run($this->commands->new()->add('--version')->build());
        } catch (Throwable $exception) {
            return HealthCheckResultDTO::error(
                $this->id(),
                $this->title(),
                $exception->getMessage(),
                'Install git, or set [statamic-git-sync.repository.git_binary] to its absolute path.',
            );
        }

        if ($result->failed()) {
            return HealthCheckResultDTO::error(
                $this->id(),
                $this->title(),
                'git --version exited with code '.$result->exitCode,
                'Check that the PHP user is allowed to execute git.',
            );
        }

        return HealthCheckResultDTO::ok($this->id(), $this->title(), $result->trimmedOutput());
    }
}
