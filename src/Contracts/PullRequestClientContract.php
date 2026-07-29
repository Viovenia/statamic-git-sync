<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Contracts;

use Viovenia\GitSync\DataTransferObjects\Git\BranchNameDTO;
use Viovenia\GitSync\DataTransferObjects\Git\RepositorySlugDTO;
use Viovenia\GitSync\DataTransferObjects\PullRequest\PullRequestDTO;
use Viovenia\GitSync\DataTransferObjects\PullRequest\PullRequestRequestDTO;
use Viovenia\GitSync\Exceptions\PullRequestFailedException;

/**
 * Talks to whichever forge hosts the repository.
 *
 * Deliberately narrow: this addon needs to know whether a pull request is
 * already open for a branch, and to open one if not. Everything else — review
 * requests, merging, comments — belongs to the forge's own interface.
 */
interface PullRequestClientContract
{
    /**
     * The open pull request for this head branch, if there is one.
     *
     * @throws PullRequestFailedException
     */
    public function findOpen(RepositorySlugDTO $repository, BranchNameDTO $head): ?PullRequestDTO;

    /**
     * @throws PullRequestFailedException
     */
    public function open(PullRequestRequestDTO $request): PullRequestDTO;
}
