<?php

declare(strict_types=1);

namespace Viovenia\GitSync\DataTransferObjects\Git;

final readonly class CommitReferenceDTO
{
    public function __construct(
        public string $sha,
        public string $subject,
        public DiffStatDTO $stat = new DiffStatDTO(0, 0),
    ) {}

    public function shortSha(): string
    {
        return substr($this->sha, 0, 7);
    }

    public function withStat(DiffStatDTO $stat): self
    {
        return new self($this->sha, $this->subject, $stat);
    }
}
