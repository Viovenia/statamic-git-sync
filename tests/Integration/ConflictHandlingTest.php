<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Tests\Integration;

use Illuminate\Support\Facades\Process;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Viovenia\GitSync\Contracts\SyncManagerContract;
use Viovenia\GitSync\DataTransferObjects\Sync\SyncContextDTO;
use Viovenia\GitSync\Enums\FailureType;
use Viovenia\GitSync\Enums\SyncOutcome;
use Viovenia\GitSync\Tests\Concerns\InteractsWithTemporaryRepository;
use Viovenia\GitSync\Tests\TestCase;

/**
 * The promise that matters most when nobody is watching: a failed integration
 * never leaves the working tree half-rebased.
 */
#[Group('integration')]
final class ConflictHandlingTest extends TestCase
{
    use InteractsWithTemporaryRepository;

    private string $remotePath;

    protected function setUp(): void
    {
        parent::setUp();

        Process::preventStrayProcesses(false);

        $this->createTemporaryRepository();
        $this->remotePath = $this->createDivergentRemote();

        $this->configure([
            'repository.path' => $this->repositoryPath,
            'content.paths' => ['content'],
            'pull.enabled' => true,
            'pull.strategy' => 'rebase',
            'push.enabled' => false,
            'push.remote' => 'origin',
        ]);
    }

    protected function tearDown(): void
    {
        $this->deleteTemporaryRepository();

        parent::tearDown();
    }

    #[Test]
    public function a_conflicting_rebase_is_aborted_and_the_tree_is_left_intact(): void
    {
        // Both sides changed the same line; rebasing cannot resolve that.
        $this->writeFile('content/home.md', "title: Local\n");

        $result = $this->app->make(SyncManagerContract::class)
            ->sync(SyncContextDTO::fromConsole(time()));

        $this->assertSame(SyncOutcome::Conflicted, $result->outcome);
        $this->assertSame(FailureType::RebaseConflict, $result->failure?->type);
        $this->assertFalse($result->retryable(), 'A conflict needs a human, not another attempt.');

        // No rebase left in progress, and the local commit still stands.
        $this->assertDirectoryDoesNotExist($this->repositoryPath.'/.git/rebase-merge');
        $this->assertDirectoryDoesNotExist($this->repositoryPath.'/.git/rebase-apply');
        $this->assertStringContainsString('Local', file_get_contents($this->repositoryPath.'/content/home.md') ?: '');
    }

    /** A remote whose history conflicts with whatever the local side commits. */
    private function createDivergentRemote(): string
    {
        $this->writeFile('content/home.md', "title: Shared\n");
        $this->git(['add', '.']);
        $this->git(['commit', '--message', 'initial']);

        $remote = $this->repositoryPath.'-remote';
        $this->git(['clone', '--bare', $this->repositoryPath, $remote]);
        $this->git(['remote', 'add', 'origin', $remote]);

        // A second clone makes the conflicting change and publishes it.
        $other = $this->repositoryPath.'-other';
        $this->git(['clone', $remote, $other]);
        file_put_contents($other.'/content/home.md', "title: Remote\n");

        foreach ([
            ['-C', $other, 'config', 'user.name', 'Other'],
            ['-C', $other, 'config', 'user.email', 'other@example.com'],
            ['-C', $other, 'add', '.'],
            ['-C', $other, 'commit', '--message', 'remote change'],
            ['-C', $other, 'push', 'origin', 'HEAD:main'],
        ] as $arguments) {
            $this->git($arguments);
        }

        return $remote;
    }
}
