<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Tests\Integration;

use Illuminate\Support\Facades\Process;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Viovenia\GitSync\Contracts\SyncManagerContract;
use Viovenia\GitSync\DataTransferObjects\Git\CommitAuthorDTO;
use Viovenia\GitSync\DataTransferObjects\Sync\SyncContextDTO;
use Viovenia\GitSync\Enums\SyncOutcome;
use Viovenia\GitSync\Tests\Concerns\InteractsWithTemporaryRepository;
use Viovenia\GitSync\Tests\TestCase;

/**
 * The whole chain against a real repository: no faked processes, real commits.
 */
#[Group('integration')]
final class CommitSyncStrategyTest extends TestCase
{
    use InteractsWithTemporaryRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createTemporaryRepository();

        // These tests are the one place where a real git process is the point.
        Process::preventStrayProcesses(false);

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
    public function it_commits_the_pending_content_with_the_trailer_and_the_author(): void
    {
        $this->writeFile('content/collections/pages/home.md');

        $result = $this->sync(new CommitAuthorDTO('Marcel', 'marcel@example.com'));

        $this->assertSame(SyncOutcome::Committed, $result->outcome, $result->failure?->message ?? '');
        $this->assertSame(1, $this->commitCount());

        $message = $this->git(['log', '-1', '--pretty=%B']);
        $this->assertStringContainsString('home.md', $message);
        $this->assertStringContainsString('Committed-by: statamic-git-sync', $message);

        $this->assertSame('Marcel <marcel@example.com>', trim($this->git(['log', '-1', '--pretty=%an <%ae>'])));
    }

    #[Test]
    public function a_clean_working_tree_produces_no_commit_at_all(): void
    {
        $this->writeFile('content/home.md');
        $this->sync();

        $result = $this->sync();

        $this->assertSame(SyncOutcome::NothingToCommit, $result->outcome);
        $this->assertSame(1, $this->commitCount());
    }

    #[Test]
    public function a_change_outside_the_allow_list_is_left_uncommitted(): void
    {
        $this->writeFile('content/home.md');
        $this->sync();

        $this->writeFile('app/Secret.php', '<?php // not content');

        $result = $this->sync();

        $this->assertSame(SyncOutcome::NothingToCommit, $result->outcome);
        $this->assertSame(1, $this->commitCount());
        // Still sitting there untracked — git collapses an untracked
        // directory to "?? app/" rather than listing the file inside it.
        $this->assertStringContainsString('app/', $this->git(['status', '--porcelain']));
    }

    #[Test]
    public function a_dry_run_reports_the_files_without_writing_anything(): void
    {
        $this->writeFile('content/home.md');

        $result = $this->sync(dryRun: true);

        $this->assertSame(SyncOutcome::Skipped, $result->outcome);
        $this->assertSame(1, $result->changes->count());
        $this->assertSame(0, $this->commitCount());
    }

    #[Test]
    public function a_deletion_is_committed_as_well(): void
    {
        $this->writeFile('content/home.md');
        $this->sync();

        unlink($this->repositoryPath.'/content/home.md');

        $result = $this->sync();

        $this->assertSame(SyncOutcome::Committed, $result->outcome);
        $this->assertSame(2, $this->commitCount());
        $this->assertSame('', trim($this->git(['status', '--porcelain'])));
    }

    #[Test]
    public function the_safety_net_command_commits_what_was_left_behind(): void
    {
        $this->writeFile('content/home.md');

        $this->artisan('statamic:git-sync --safety-net')->assertExitCode(0);

        $this->assertSame(1, $this->commitCount());
    }

    private function sync(?CommitAuthorDTO $author = null, bool $dryRun = false): \Viovenia\GitSync\DataTransferObjects\Sync\SyncResultDTO
    {
        return $this->app->make(SyncManagerContract::class)->sync(
            SyncContextDTO::fromConsole(time(), $dryRun, $author),
        );
    }
}
