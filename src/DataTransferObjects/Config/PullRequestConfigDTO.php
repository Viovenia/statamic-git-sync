<?php

declare(strict_types=1);

namespace Viovenia\GitSync\DataTransferObjects\Config;

use Viovenia\GitSync\DataTransferObjects\Git\BranchNameDTO;
use Viovenia\GitSync\DataTransferObjects\Git\RepositorySlugDTO;
use Viovenia\GitSync\Enums\PullRequestDriver;

final readonly class PullRequestConfigDTO
{
    /** @param list<string> $labels */
    public function __construct(
        public PullRequestDriver $driver,
        public string $apiUrl,
        public ?string $token,
        /** Null derives the slug from the remote URL, which git already knows. */
        public ?RepositorySlugDTO $repository,
        /** The branch the pull request asks to merge into. */
        public BranchNameDTO $base,
        public string $titleTemplate,
        public string $bodyTemplate,
        public bool $draft,
        public array $labels,
        /** Append to an open pull request rather than opening one per sync. */
        public bool $reuseOpen,
    ) {}
}
