<?php

declare(strict_types=1);

namespace Viovenia\GitSync\DataTransferObjects\Git;

final readonly class CommitReferenceDTO
{
    public function __construct(
        public string $sha,
        public string $subject,
    ) {}

    public function shortSha(): string
    {
        return substr($this->sha, 0, 7);
    }
}
