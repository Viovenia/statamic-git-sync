<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Contracts;

use Viovenia\GitSync\DataTransferObjects\Content\ChangeSetDTO;
use Viovenia\GitSync\DataTransferObjects\Content\PathCollectionDTO;
use Viovenia\GitSync\DataTransferObjects\Git\BranchNameDTO;
use Viovenia\GitSync\DataTransferObjects\Git\CommitAuthorDTO;
use Viovenia\GitSync\DataTransferObjects\Git\CommitReferenceDTO;
use Viovenia\GitSync\DataTransferObjects\Git\PushResultDTO;
use Viovenia\GitSync\Enums\PullStrategy;
use Viovenia\GitSync\Exceptions\GitCommandFailedException;
use Viovenia\GitSync\Exceptions\RebaseConflictException;

/**
 * Every git verb this addon knows, and nothing else.
 *
 * Deliberately free of Statamic vocabulary: this talks about paths, branches
 * and commits, never about entries or collections. That is what makes it
 * testable against a plain temporary repository.
 */
interface GitRepositoryContract
{
    public function path(): string;

    /** Uncommitted changes limited to the given paths. */
    public function status(PathCollectionDTO $paths): ChangeSetDTO;

    public function stage(PathCollectionDTO $paths): void;

    /**
     * Commit **only** the given paths.
     *
     * Not whatever happens to be in the index: somebody may have staged an
     * unrelated file by hand, and it must not ride along under a commit
     * message that says "content".
     *
     * @throws GitCommandFailedException
     */
    public function commit(
        string $message,
        ?CommitAuthorDTO $author,
        bool $allowEmpty,
        bool $sign,
        PathCollectionDTO $paths,
    ): CommitReferenceDTO;

    /**
     * Which git operation is half-finished in this repository, if any:
     * "merge", "rebase" or "cherry-pick".
     */
    public function pendingOperation(): ?string;

    public function currentBranch(): BranchNameDTO;

    public function hasRemote(string $remote): bool;

    /** The configured URL of a remote, or null when it has none. */
    public function remoteUrl(string $remote): ?string;

    /** The `user.name` / `user.email` git is configured with, if any. */
    public function identity(): ?CommitAuthorDTO;

    public function hasCommits(): bool;

    /**
     * Integrate the remote branch into the local one.
     *
     * Implementations abort a conflicting rebase or merge before throwing,
     * so the working tree is always left the way it was found.
     *
     * @throws RebaseConflictException
     * @throws GitCommandFailedException
     */
    public function pull(PullStrategy $strategy, string $remote, BranchNameDTO $branch): void;

    /**
     * @throws GitCommandFailedException
     */
    public function push(string $remote, BranchNameDTO $branch, bool $forceWithLease): PushResultDTO;
}
