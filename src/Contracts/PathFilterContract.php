<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Contracts;

interface PathFilterContract
{
    /** Whether a repository-relative path is allowed to be committed. */
    public function matches(string $path): bool;
}
