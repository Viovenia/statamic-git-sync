<?php

declare(strict_types=1);

namespace Viovenia\GitSync\DataTransferObjects\Git;

final readonly class PushResultDTO
{
    public function __construct(
        public string $remote,
        public BranchNameDTO $branch,
        public bool $forced,
    ) {}

    public function describe(): string
    {
        return sprintf('%s %s%s', $this->remote, $this->branch->value, $this->forced ? ' (forced)' : '');
    }
}
