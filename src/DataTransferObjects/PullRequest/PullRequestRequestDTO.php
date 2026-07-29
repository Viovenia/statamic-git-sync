<?php

declare(strict_types=1);

namespace Viovenia\GitSync\DataTransferObjects\PullRequest;

use Viovenia\GitSync\DataTransferObjects\Git\BranchNameDTO;
use Viovenia\GitSync\DataTransferObjects\Git\RepositorySlugDTO;

/** Everything needed to open a pull request, independent of the forge. */
final readonly class PullRequestRequestDTO
{
    /** @param list<string> $labels */
    public function __construct(
        public RepositorySlugDTO $repository,
        public BranchNameDTO $head,
        public BranchNameDTO $base,
        public string $title,
        public string $body,
        public bool $draft,
        public array $labels,
    ) {}
}
