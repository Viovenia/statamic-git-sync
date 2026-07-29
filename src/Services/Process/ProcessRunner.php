<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Services\Process;

use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\Process;
use Throwable;
use Viovenia\GitSync\Contracts\ProcessRunnerContract;
use Viovenia\GitSync\DataTransferObjects\Git\CommandResultDTO;
use Viovenia\GitSync\DataTransferObjects\Git\GitCommandDTO;
use Viovenia\GitSync\DataTransferObjects\Sync\DurationDTO;
use Viovenia\GitSync\Exceptions\GitBinaryNotFoundException;

/**
 * The single place in this package that starts a process.
 *
 * Everything else goes through GitRepository, which goes through here. That
 * is what lets `Process::fake()` cover the entire addon in tests, and what
 * lets `Process::preventStrayProcesses()` prove there is no second path.
 *
 * A non-zero exit code is data, not an exception — the caller decides what a
 * failed `git status` means. Only a binary that cannot be started at all
 * raises, because there is nothing sensible to return in that case.
 */
final class ProcessRunner implements ProcessRunnerContract
{
    /** Conventional shell exit code for "killed after timing out". */
    private const TIMEOUT_EXIT_CODE = 124;

    public function run(GitCommandDTO $command): CommandResultDTO
    {
        $start = microtime(true);

        try {
            $result = Process::path($command->workingDirectory)
                ->timeout($command->timeout)
                ->env($command->environment)
                ->run($command->commandLine());
        } catch (ProcessTimedOutException $exception) {
            return new CommandResultDTO(
                command: $command,
                exitCode: self::TIMEOUT_EXIT_CODE,
                output: '',
                errorOutput: sprintf(
                    'Timed out after %d seconds. Raise [statamic-git-sync.repository.timeout] if this is a large repository. (%s)',
                    $command->timeout,
                    $exception->getMessage(),
                ),
                duration: DurationDTO::between($start, microtime(true)),
            );
        } catch (Throwable $exception) {
            if ($this->looksLikeMissingBinary($exception)) {
                throw GitBinaryNotFoundException::forBinary($command->binary, $exception);
            }

            throw $exception;
        }

        return new CommandResultDTO(
            command: $command,
            exitCode: $result->exitCode() ?? 1,
            output: $result->output(),
            errorOutput: $result->errorOutput(),
            duration: DurationDTO::between($start, microtime(true)),
        );
    }

    private function looksLikeMissingBinary(Throwable $exception): bool
    {
        $message = strtolower($exception->getMessage());

        foreach (['executable not found', 'no such file or directory', 'command not found', 'failed to start'] as $needle) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }
}
