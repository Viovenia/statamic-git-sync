<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Services\Git;

use Viovenia\GitSync\Contracts\GitRepositoryLocatorContract;
use Viovenia\GitSync\DataTransferObjects\Config\RepositoryConfigDTO;
use Viovenia\GitSync\Exceptions\RepositoryNotFoundException;

/**
 * Works out which directory git commands run in, and whether it is a repository.
 *
 * `.git` is checked as a path rather than a directory on purpose: in a git
 * worktree it is a file pointing elsewhere, and refusing to work there would
 * be wrong.
 */
final class GitRepositoryLocator implements GitRepositoryLocatorContract
{
    public function __construct(
        private readonly RepositoryConfigDTO $config,
        private readonly string $basePath,
    ) {}

    public function path(): string
    {
        $path = $this->config->path ?? $this->basePath;

        return rtrim($path, DIRECTORY_SEPARATOR);
    }

    public function exists(): bool
    {
        return file_exists($this->path().DIRECTORY_SEPARATOR.'.git');
    }

    public function writable(): bool
    {
        return is_writable($this->path());
    }

    public function ensureExists(): void
    {
        if (! $this->exists()) {
            throw RepositoryNotFoundException::atPath($this->path());
        }

        if (! $this->writable()) {
            throw RepositoryNotFoundException::notWritable($this->path());
        }
    }
}
