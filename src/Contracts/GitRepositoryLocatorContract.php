<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Contracts;

use Viovenia\GitSync\Exceptions\RepositoryNotFoundException;

interface GitRepositoryLocatorContract
{
    /** The absolute path every git command runs in. */
    public function path(): string;

    /** Whether that path actually holds a git repository. */
    public function exists(): bool;

    public function writable(): bool;

    /**
     * @throws RepositoryNotFoundException
     */
    public function ensureExists(): void;
}
