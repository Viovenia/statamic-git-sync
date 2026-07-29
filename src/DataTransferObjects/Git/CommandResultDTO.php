<?php

declare(strict_types=1);

namespace Viovenia\GitSync\DataTransferObjects\Git;

use Viovenia\GitSync\Concerns\RedactsCredentials;
use Viovenia\GitSync\DataTransferObjects\Sync\DurationDTO;

final readonly class CommandResultDTO
{
    use RedactsCredentials;

    public function __construct(
        public GitCommandDTO $command,
        public int $exitCode,
        public string $output,
        public string $errorOutput,
        public DurationDTO $duration,
    ) {}

    public function successful(): bool
    {
        return $this->exitCode === 0;
    }

    public function failed(): bool
    {
        return ! $this->successful();
    }

    /**
     * Standard output split into non-empty trimmed lines.
     *
     * @return list<string>
     */
    public function lines(): array
    {
        $lines = preg_split('/\R/', trim($this->output)) ?: [];

        return array_values(array_filter(
            array_map(trim(...), $lines),
            static fn (string $line): bool => $line !== '',
        ));
    }

    public function trimmedOutput(): string
    {
        return trim($this->output);
    }

    public function redactedCommandLine(): string
    {
        return $this->command->describe();
    }

    /** Error output, redacted and shortened enough for an exception message. */
    public function shortError(): string
    {
        $error = trim($this->errorOutput) !== '' ? $this->errorOutput : $this->output;
        $error = $this->redact(trim($error));

        return mb_strlen($error) > 500
            ? mb_substr($error, 0, 500).'…'
            : $error;
    }

    /** Redacted combined output, for log context. */
    public function redactedOutput(): string
    {
        return $this->redact(trim($this->output."\n".$this->errorOutput));
    }
}
