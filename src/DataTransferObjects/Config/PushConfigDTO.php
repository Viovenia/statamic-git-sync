<?php

declare(strict_types=1);

namespace Viovenia\GitSync\DataTransferObjects\Config;

use Viovenia\GitSync\DataTransferObjects\Git\BranchNameDTO;

final readonly class PushConfigDTO
{
    public function __construct(
        public bool $enabled,
        public string $remote,
        /** Null means "whatever branch the repository is on right now". */
        public ?BranchNameDTO $branch,
        public bool $forceWithLease,
    ) {}
}
