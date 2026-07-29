<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Contracts;

use Viovenia\GitSync\DataTransferObjects\Git\CommitAuthorDTO;

interface CommitAuthorResolverContract
{
    /**
     * Who is credited with the commit.
     *
     * Null when nobody could be determined and no fallback is configured —
     * git then uses whatever identity it is configured with.
     */
    public function resolve(): ?CommitAuthorDTO;
}
