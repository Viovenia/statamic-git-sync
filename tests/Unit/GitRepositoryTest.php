<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Tests\Unit;

use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use PHPUnit\Framework\Attributes\Test;
use Viovenia\GitSync\Contracts\GitRepositoryContract;
use Viovenia\GitSync\DataTransferObjects\Content\PathCollectionDTO;
use Viovenia\GitSync\DataTransferObjects\Git\BranchNameDTO;
use Viovenia\GitSync\DataTransferObjects\Git\CommitAuthorDTO;
use Viovenia\GitSync\Enums\PullStrategy;
use Viovenia\GitSync\Exceptions\GitCommandFailedException;
use Viovenia\GitSync\Exceptions\RebaseConflictException;
use Viovenia\GitSync\Tests\TestCase;

final class GitRepositoryTest extends TestCase
{
    #[Test]
    public function it_asks_git_only_about_the_configured_paths(): void
    {
        Process::fake(['*' => Process::result('')]);

        $this->repository()->status(PathCollectionDTO::of('content', 'users'));

        Process::assertRan(fn (PendingProcess $process): bool => $process->command === [
            'git', 'status', '--porcelain=v1', '-z', '--untracked-files=all', '--', 'content', 'users',
        ]);
    }

    #[Test]
    public function committing_passes_the_author_through_and_reads_back_the_new_commit(): void
    {
        Process::fake([
            '*' => Process::result("2f1a3c4d5e6f7a8b9c0d1e2f3a4b5c6d7e8f9a0b\ncontent: home.md"),
        ]);

        $commit = $this->repository()->commit(
            'content: home.md',
            new CommitAuthorDTO('Marcel', 'marcel@example.com'),
            allowEmpty: false,
            sign: false,
            paths: PathCollectionDTO::of('content/home.md'),
        );

        // --only, and the paths after the separator: anything else somebody
        // staged by hand must stay out of a content commit.
        Process::assertRan(fn (PendingProcess $process): bool => $process->command === [
            'git', 'commit', '--message', 'content: home.md', '--only',
            '--author', 'Marcel <marcel@example.com>', '--', 'content/home.md',
        ]);

        $this->assertSame('2f1a3c4', $commit->shortSha());
        $this->assertSame('content: home.md', $commit->subject);
    }

    #[Test]
    public function a_failing_command_becomes_an_exception_carrying_the_result(): void
    {
        Process::fake(['*' => Process::result(output: '', errorOutput: 'fatal: not a git repository', exitCode: 128)]);

        try {
            $this->repository()->stage(PathCollectionDTO::of('content'));
            $this->fail('Expected the failure to surface.');
        } catch (GitCommandFailedException $exception) {
            $this->assertSame(128, $exception->commandResult()?->exitCode);
            $this->assertStringContainsString('not a git repository', $exception->getMessage());
            $this->assertTrue($exception->retryable());
        }
    }

    #[Test]
    public function a_conflicting_pull_is_aborted_before_the_exception_escapes(): void
    {
        // Symfony quotes every argument, so the pattern matches loosely.
        Process::fake([
            '*pull*' => Process::result(output: 'CONFLICT (content): Merge conflict in content/home.md', exitCode: 1),
            '*' => Process::result(''),
        ]);

        try {
            $this->repository()->pull(PullStrategy::Rebase, 'origin', BranchNameDTO::fromString('main'));
            $this->fail('Expected the conflict to surface.');
        } catch (RebaseConflictException $exception) {
            $this->assertFalse($exception->retryable(), 'A conflict will fail identically next time.');
        }

        Process::assertRan(fn (PendingProcess $process): bool => $process->command === [
            'git', 'rebase', '--abort',
        ]);
    }

    #[Test]
    public function staging_nothing_runs_no_command_at_all(): void
    {
        Process::fake();

        $this->repository()->stage(PathCollectionDTO::empty());

        Process::assertNothingRan();
    }

    #[Test]
    public function force_with_lease_is_only_passed_when_asked_for(): void
    {
        Process::fake(['*' => Process::result('')]);

        $this->repository()->push('origin', BranchNameDTO::fromString('main'), forceWithLease: false);

        Process::assertRan(fn (PendingProcess $process): bool => $process->command === [
            'git', 'push', 'origin', 'main',
        ]);
    }

    private function repository(): GitRepositoryContract
    {
        return $this->app->make(GitRepositoryContract::class);
    }
}
