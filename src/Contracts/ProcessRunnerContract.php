<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Contracts;

use Viovenia\GitSync\DataTransferObjects\Git\CommandResultDTO;
use Viovenia\GitSync\DataTransferObjects\Git\GitCommandDTO;
use Viovenia\GitSync\Exceptions\GitBinaryNotFoundException;

/**
 * The only way this package is allowed to touch the operating system.
 *
 * Implementations never throw on a non-zero exit code — that is a result,
 * not an exception. They throw only when the binary could not be run at all.
 */
interface ProcessRunnerContract
{
    /**
     * @throws GitBinaryNotFoundException
     */
    public function run(GitCommandDTO $command): CommandResultDTO;
}
