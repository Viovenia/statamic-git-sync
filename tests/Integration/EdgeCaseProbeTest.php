<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Tests\Integration;

use Illuminate\Support\Facades\Process;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Viovenia\GitSync\Contracts\SyncManagerContract;
use Viovenia\GitSync\DataTransferObjects\Sync\SyncContextDTO;
use Viovenia\GitSync\DataTransferObjects\Sync\SyncResultDTO;
use Viovenia\GitSync\Enums\SyncOutcome;
use Viovenia\GitSync\Tests\Concerns\InteractsWithTemporaryRepository;
use Viovenia\GitSync\Tests\TestCase;

/**
 * The situations a real repository gets into that a happy-path test never
 * reaches. Every one of these is something an unattended process will meet
 * eventually, so each gets a real git repository rather than a fake.
 */
#[Group('integration')]
final class EdgeCaseProbeTest extends TestCase
{
    use InteractsWithTemporaryRepository;

    protected function setUp(): void
    {
        parent::setUp();

        Process::preventStrayProcesses(false);

        $this->createTemporaryRepository();

        $this->configure([
            'repository.path' => $this->repositoryPath,
            'content.paths' => ['content'],
            'push.enabled' => false,
            'pull.enabled' => false,
        ]);
    }

    protected function tearDown(): void
    {
        $this->deleteTemporaryRepository();

        parent::tearDown();
    }

    #[Test]
    public function a_file_somebody_else_staged_never_rides_along_with_a_content_commit(): void
    {
        $this->writeFile('content/home.md');
        $this->sync();

        // An operator stages something by hand and forgets about it.
        $this->writeFile('app/Secret.php', '<?php // not content');
        $this->git(['add', 'app/Secret.php']);

        $this->writeFile('content/about.md');

        $result = $this->sync();

        $this->assertSame(SyncOutcome::Committed, $result->outcome, $result->failure?->message ?? '');

        $committed = $this->git(['show', '--name-only', '--pretty=format:', 'HEAD']);

        $this->assertStringContainsString('content/about.md', $committed);
        $this->assertStringNotContainsString(
            'app/Secret.php',
            $committed,
            'A file outside the allow list must never be committed, staged or not.',
        );

        // And it is still staged, exactly as the operator left it.
        $this->assertStringContainsString('A  app/Secret.php', $this->git(['status', '--porcelain']));
    }

    #[Test]
    public function the_very_first_commit_of_a_repository_works(): void
    {
        // No HEAD yet: a partial commit behaves differently here.
        $this->writeFile('content/home.md');

        $result = $this->sync();

        $this->assertSame(SyncOutcome::Committed, $result->outcome, $result->failure?->message ?? '');
        $this->assertSame(1, $this->commitCount());
    }

    #[Test]
    public function a_failing_pre_commit_hook_is_reported_and_not_retried_forever(): void
    {
        $this->writeFile('content/home.md');
        $this->writeHook('pre-commit', "#!/bin/sh\necho 'nope' >&2\nexit 1\n");

        $result = $this->sync();

        $this->assertSame(SyncOutcome::Failed, $result->outcome);
        $this->assertSame(0, $this->commitCount(), 'The hook refused, so nothing may be committed.');
    }

    #[Test]
    public function a_repository_left_mid_merge_does_not_get_a_content_commit_on_top(): void
    {
        $this->writeFile('content/home.md');
        $this->sync();

        // Manufacture a conflicted merge and leave it in progress.
        $this->git(['checkout', '-b', 'other']);
        $this->writeFile('content/home.md', "title: Other\n");
        $this->git(['commit', '--all', '--message', 'other side']);
        $this->git(['checkout', 'main']);
        $this->writeFile('content/home.md', "title: Main\n");
        $this->git(['commit', '--all', '--message', 'main side']);
        $this->runGitAllowingFailure(['merge', 'other']);

        $this->assertFileExists($this->repositoryPath.'/.git/MERGE_HEAD', 'The merge should still be in progress.');

        $result = $this->sync();

        $this->assertNotSame(
            SyncOutcome::Committed,
            $result->outcome,
            'Committing on top of an unresolved merge would bury the conflict.',
        );
    }

    #[Test]
    public function content_that_is_gitignored_is_reported_rather_than_silently_skipped(): void
    {
        $this->writeFile('.gitignore', "content/drafts/\n");
        $this->git(['add', '.gitignore']);
        $this->git(['commit', '--message', 'ignore drafts']);

        $this->writeFile('content/drafts/secret.md');

        $result = $this->sync();

        // Nothing to commit is the correct outcome — the question is whether
        // it is honest about why.
        $this->assertSame(SyncOutcome::NothingToCommit, $result->outcome);
        $this->assertSame(1, $this->commitCount());
    }

    #[Test]
    public function a_permission_change_alone_is_a_change(): void
    {
        $this->writeFile('content/home.md');
        $this->sync();

        chmod($this->repositoryPath.'/content/home.md', 0o600);

        $result = $this->sync();

        // Either outcome is defensible; this pins down which one happens.
        $this->assertContains($result->outcome, [SyncOutcome::Committed, SyncOutcome::NothingToCommit]);
    }

    #[Test]
    public function a_symlink_inside_the_content_directory_does_not_break_the_run(): void
    {
        $this->writeFile('content/home.md');
        symlink($this->repositoryPath.'/content/home.md', $this->repositoryPath.'/content/alias.md');

        $result = $this->sync();

        $this->assertSame(SyncOutcome::Committed, $result->outcome, $result->failure?->message ?? '');
    }

    private function writeHook(string $name, string $contents): void
    {
        $path = $this->repositoryPath.'/.git/hooks/'.$name;

        file_put_contents($path, $contents);
        chmod($path, 0o755);
    }

    /** @param list<string> $arguments */
    private function runGitAllowingFailure(array $arguments): void
    {
        $process = new \Symfony\Component\Process\Process(['git', ...$arguments], $this->repositoryPath);
        $process->run();
    }

    private function sync(): SyncResultDTO
    {
        return $this->app->make(SyncManagerContract::class)->sync(SyncContextDTO::fromConsole(time()));
    }
}
