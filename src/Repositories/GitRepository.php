<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Repositories;

use Viovenia\GitSync\Contracts\GitRepositoryContract;
use Viovenia\GitSync\Contracts\GitRepositoryLocatorContract;
use Viovenia\GitSync\Contracts\ProcessRunnerContract;
use Viovenia\GitSync\DataTransferObjects\Content\ChangeSetDTO;
use Viovenia\GitSync\DataTransferObjects\Content\PathCollectionDTO;
use Viovenia\GitSync\DataTransferObjects\Git\BranchNameDTO;
use Viovenia\GitSync\DataTransferObjects\Git\CommandResultDTO;
use Viovenia\GitSync\DataTransferObjects\Git\CommitAuthorDTO;
use Viovenia\GitSync\DataTransferObjects\Git\CommitReferenceDTO;
use Viovenia\GitSync\DataTransferObjects\Git\DiffStatDTO;
use Viovenia\GitSync\DataTransferObjects\Git\GitCommandDTO;
use Viovenia\GitSync\DataTransferObjects\Git\PushResultDTO;
use Viovenia\GitSync\Enums\PullStrategy;
use Viovenia\GitSync\Exceptions\GitCommandFailedException;
use Viovenia\GitSync\Exceptions\RebaseConflictException;
use Viovenia\GitSync\Services\Git\GitCommandBuilder;
use Viovenia\GitSync\Services\Git\PorcelainParser;

/**
 * Every git verb this addon uses, and nowhere else.
 *
 * Nothing in here knows what an entry is. It takes paths and branches and
 * returns DTOs, which is precisely why it can be tested against a plain
 * temporary repository created with `git init`.
 */
final class GitRepository implements GitRepositoryContract
{
    public function __construct(
        private readonly ProcessRunnerContract $processes,
        private readonly GitCommandBuilder $commands,
        private readonly PorcelainParser $parser,
        private readonly GitRepositoryLocatorContract $locator,
    ) {}

    public function path(): string
    {
        return $this->locator->path();
    }

    public function status(PathCollectionDTO $paths): ChangeSetDTO
    {
        $result = $this->mustRun(
            $this->commands->new()
                ->add('status', '--porcelain=v1', '-z', '--untracked-files=all')
                ->paths($paths)
                ->build(),
        );

        return $this->parser->parse($result->output);
    }

    public function stage(PathCollectionDTO $paths): void
    {
        if ($paths->isEmpty()) {
            return;
        }

        $this->mustRun(
            $this->commands->new()
                ->add('add', '--all')
                ->paths($paths)
                ->build(),
        );
    }

    public function commit(
        string $message,
        ?CommitAuthorDTO $author,
        bool $allowEmpty,
        bool $sign,
        PathCollectionDTO $paths,
    ): CommitReferenceDTO {
        // `--only` restricts the commit to these paths and ignores whatever
        // else sits in the index. Without it, a file somebody staged by hand
        // and forgot about would be committed under a "content" message — and
        // pushed, if pushing is on.
        $command = $this->commands->new()
            ->add('commit', '--message', $message)
            ->addIf(! $paths->isEmpty(), '--only')
            ->addIf($allowEmpty, '--allow-empty')
            ->addIf($sign, '--gpg-sign')
            ->addIf($author !== null, '--author', $author?->toGitIdentity() ?? '')
            ->paths($paths)
            ->build();

        $this->mustRun($command);

        return $this->head();
    }

    public function pendingOperation(): ?string
    {
        $git = $this->locator->path().DIRECTORY_SEPARATOR.'.git';

        // Read from disk rather than parsed from `git status`: these marker
        // files are git's own record and their names have been stable for
        // well over a decade.
        return match (true) {
            file_exists($git.DIRECTORY_SEPARATOR.'MERGE_HEAD') => 'merge',
            file_exists($git.DIRECTORY_SEPARATOR.'rebase-merge'),
            file_exists($git.DIRECTORY_SEPARATOR.'rebase-apply') => 'rebase',
            file_exists($git.DIRECTORY_SEPARATOR.'CHERRY_PICK_HEAD') => 'cherry-pick',
            default => null,
        };
    }

    public function currentBranch(): BranchNameDTO
    {
        $result = $this->mustRun(
            $this->commands->new()->add('rev-parse', '--abbrev-ref', 'HEAD')->build(),
        );

        return BranchNameDTO::fromString($result->trimmedOutput());
    }

    public function hasRemote(string $remote): bool
    {
        $result = $this->processes->run(
            $this->commands->new()->add('remote', 'get-url', $remote)->build(),
        );

        return $result->successful();
    }

    public function remoteUrl(string $remote): ?string
    {
        $result = $this->processes->run(
            $this->commands->new()->add('remote', 'get-url', $remote)->build(),
        );

        if ($result->failed()) {
            return null;
        }

        $url = $result->trimmedOutput();

        return $url === '' ? null : $url;
    }

    public function identity(): ?CommitAuthorDTO
    {
        $name = $this->processes->run($this->commands->new()->add('config', 'user.name')->build());
        $email = $this->processes->run($this->commands->new()->add('config', 'user.email')->build());

        if ($name->failed() || $email->failed()) {
            return null;
        }

        $nameValue = $name->trimmedOutput();
        $emailValue = $email->trimmedOutput();

        if ($nameValue === '' || $emailValue === '') {
            return null;
        }

        return new CommitAuthorDTO($nameValue, $emailValue);
    }

    public function hasCommits(): bool
    {
        return $this->processes
            ->run($this->commands->new()->add('rev-parse', '--verify', 'HEAD')->build())
            ->successful();
    }

    public function pull(PullStrategy $strategy, string $remote, BranchNameDTO $branch): void
    {
        $result = $this->processes->run(
            $this->commands->new()
                ->add('pull', $strategy->flag(), $remote, $branch->value)
                ->build(),
        );

        if ($result->successful()) {
            return;
        }

        // Whatever went wrong, leave the working tree the way we found it.
        // The abort is best effort: if there was nothing in progress it fails
        // harmlessly, and that failure must not mask the original one.
        $this->processes->run(
            $this->commands->new()->add($strategy->abortCommand(), '--abort')->build(),
        );

        if ($this->looksLikeConflict($result)) {
            throw RebaseConflictException::during($strategy, $result);
        }

        throw GitCommandFailedException::from($result);
    }

    public function push(string $remote, BranchNameDTO $branch, bool $forceWithLease): PushResultDTO
    {
        $this->mustRun(
            $this->commands->new()
                ->add('push')
                ->addIf($forceWithLease, '--force-with-lease')
                ->add($remote, $branch->value)
                ->build(),
        );

        return new PushResultDTO($remote, $branch, $forceWithLease);
    }

    /**
     * The commit HEAD points at right now, with its line counts.
     *
     * One `show` rather than a `log` plus a `diff`: the sha, the subject and
     * the numstat all come out of the same invocation.
     */
    private function head(): CommitReferenceDTO
    {
        $result = $this->mustRun(
            $this->commands->new()->add('show', '--numstat', '--pretty=format:%H%n%s')->build(),
        );

        $lines = $result->lines();

        return new CommitReferenceDTO(
            sha: $lines[0] ?? '',
            subject: $lines[1] ?? '',
            stat: DiffStatDTO::fromNumstat(array_slice($lines, 2)),
        );
    }

    private function looksLikeConflict(CommandResultDTO $result): bool
    {
        $haystack = strtolower($result->output."\n".$result->errorOutput);

        foreach (['conflict', 'could not apply', 'automatic merge failed', 'needs merge'] as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function mustRun(GitCommandDTO $command): CommandResultDTO
    {
        $result = $this->processes->run($command);

        if ($result->failed()) {
            throw GitCommandFailedException::from($result);
        }

        return $result;
    }
}
