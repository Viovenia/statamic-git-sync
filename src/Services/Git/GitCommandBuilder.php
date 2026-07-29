<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Services\Git;

use Viovenia\GitSync\Contracts\GitRepositoryLocatorContract;
use Viovenia\GitSync\DataTransferObjects\Config\RepositoryConfigDTO;
use Viovenia\GitSync\DataTransferObjects\Content\PathCollectionDTO;
use Viovenia\GitSync\DataTransferObjects\Git\GitCommandDTO;

/**
 * Assembles git invocations, one argument at a time.
 *
 * Immutable and chainable: every method returns a fresh builder, so the
 * instance held by the container can never accumulate arguments from a
 * previous call.
 *
 *     $builder->new()->add('status', '--porcelain=v1', '-z')->paths($paths)->build()
 */
final class GitCommandBuilder
{
    /** @param list<string> $arguments */
    private function __construct(
        private readonly RepositoryConfigDTO $config,
        private readonly GitRepositoryLocatorContract $locator,
        private readonly array $arguments = [],
    ) {}

    public static function for(RepositoryConfigDTO $config, GitRepositoryLocatorContract $locator): self
    {
        return new self($config, $locator);
    }

    /** Starts a fresh command. */
    public function new(): self
    {
        return new self($this->config, $this->locator);
    }

    public function add(string ...$arguments): self
    {
        return new self($this->config, $this->locator, [...$this->arguments, ...array_values($arguments)]);
    }

    public function addIf(bool $condition, string ...$arguments): self
    {
        return $condition ? $this->add(...$arguments) : $this;
    }

    /**
     * Appends paths after a `--` separator.
     *
     * The separator is what stops a path that happens to look like a flag,
     * or like a branch name, from being read as one.
     */
    public function paths(PathCollectionDTO $paths): self
    {
        if ($paths->isEmpty()) {
            return $this;
        }

        return $this->add('--', ...$paths->all());
    }

    public function build(): GitCommandDTO
    {
        return new GitCommandDTO(
            binary: $this->config->gitBinary,
            arguments: $this->arguments,
            workingDirectory: $this->locator->path(),
            timeout: $this->config->timeout,
            environment: $this->config->environment,
        );
    }
}
