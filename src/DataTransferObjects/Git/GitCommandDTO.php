<?php

declare(strict_types=1);

namespace Viovenia\GitSync\DataTransferObjects\Git;

use Viovenia\GitSync\Concerns\RedactsCredentials;

final readonly class GitCommandDTO
{
    use RedactsCredentials;

    /**
     * @param  list<string>  $arguments
     * @param  array<string, string>  $environment
     */
    public function __construct(
        public string $binary,
        public array $arguments,
        public string $workingDirectory,
        public int $timeout,
        public array $environment,
    ) {}

    /**
     * The full argv, ready to hand to a process runner.
     *
     * Passing an array rather than a string means no shell is involved, so
     * paths with spaces, quotes or newlines cannot break out of an argument.
     *
     * @return list<string>
     */
    public function commandLine(): array
    {
        return [$this->binary, ...$this->arguments];
    }

    /** Human readable, safe to log. */
    public function describe(): string
    {
        return $this->redact(implode(' ', $this->commandLine()));
    }

    /** The git subcommand, e.g. "status" or "commit". */
    public function verb(): string
    {
        return $this->arguments[0] ?? '';
    }
}
