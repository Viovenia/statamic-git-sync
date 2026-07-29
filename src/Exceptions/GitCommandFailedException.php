<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Exceptions;

use Viovenia\GitSync\DataTransferObjects\Git\CommandResultDTO;
use Viovenia\GitSync\Enums\FailureType;

final class GitCommandFailedException extends GitSyncException
{
    private function __construct(string $message, private readonly CommandResultDTO $result)
    {
        parent::__construct($message);
    }

    public static function from(CommandResultDTO $result): self
    {
        return new self(sprintf(
            'Command `%s` exited with code %d: %s',
            $result->redactedCommandLine(),
            $result->exitCode,
            $result->shortError(),
        ), $result);
    }

    public function commandResult(): CommandResultDTO
    {
        return $this->result;
    }

    public function failureType(): FailureType
    {
        return FailureType::CommandFailed;
    }
}
