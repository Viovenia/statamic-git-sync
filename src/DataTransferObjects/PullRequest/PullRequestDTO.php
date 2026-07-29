<?php

declare(strict_types=1);

namespace Viovenia\GitSync\DataTransferObjects\PullRequest;

use Viovenia\GitSync\DataTransferObjects\Git\BranchNameDTO;

final readonly class PullRequestDTO
{
    public function __construct(
        public int $number,
        public string $url,
        public string $title,
        public BranchNameDTO $head,
        public BranchNameDTO $base,
        public bool $draft,
        /** False when this run found an existing open pull request instead of opening one. */
        public bool $openedNow,
    ) {}

    public function reusedExisting(): self
    {
        return new self($this->number, $this->url, $this->title, $this->head, $this->base, $this->draft, false);
    }

    public function describe(): string
    {
        return sprintf('#%d %s', $this->number, $this->url);
    }
}
