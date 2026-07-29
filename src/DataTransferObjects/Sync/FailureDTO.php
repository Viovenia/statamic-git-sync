<?php

declare(strict_types=1);

namespace Viovenia\GitSync\DataTransferObjects\Sync;

use Throwable;
use Viovenia\GitSync\DataTransferObjects\Git\CommandResultDTO;
use Viovenia\GitSync\Enums\FailureType;
use Viovenia\GitSync\Exceptions\GitSyncException;

final readonly class FailureDTO
{
    public function __construct(
        public FailureType $type,
        public string $message,
        public bool $retryable,
        public ?CommandResultDTO $command = null,
    ) {}

    public static function fromThrowable(Throwable $throwable): self
    {
        if ($throwable instanceof GitSyncException) {
            return new self(
                $throwable->failureType(),
                $throwable->getMessage(),
                $throwable->retryable(),
                $throwable->commandResult(),
            );
        }

        return new self(
            FailureType::Unknown,
            $throwable->getMessage(),
            FailureType::Unknown->retryable(),
        );
    }

    /** @return array<string, string|int|bool> */
    public function toLogContext(): array
    {
        $context = [
            'type' => $this->type->value,
            'retryable' => $this->retryable,
            'message' => $this->message,
        ];

        if ($this->command !== null) {
            $context['command'] = $this->command->redactedCommandLine();
            $context['exit_code'] = $this->command->exitCode;
            $context['output'] = $this->command->redactedOutput();
        }

        return $context;
    }
}
