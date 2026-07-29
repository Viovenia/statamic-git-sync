<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Tests\Feature;

use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\Test;
use Viovenia\GitSync\Contracts\HistoryRepositoryContract;
use Viovenia\GitSync\DataTransferObjects\Content\ChangedFileDTO;
use Viovenia\GitSync\DataTransferObjects\Content\ChangeSetDTO;
use Viovenia\GitSync\DataTransferObjects\Git\CommitReferenceDTO;
use Viovenia\GitSync\DataTransferObjects\Sync\DurationDTO;
use Viovenia\GitSync\DataTransferObjects\Sync\FailureDTO;
use Viovenia\GitSync\DataTransferObjects\Sync\SyncContextDTO;
use Viovenia\GitSync\DataTransferObjects\Sync\SyncResultDTO;
use Viovenia\GitSync\Enums\FailureType;
use Viovenia\GitSync\Enums\FileStatus;
use Viovenia\GitSync\Enums\SyncOutcome;
use Viovenia\GitSync\Events\SyncCompleted;
use Viovenia\GitSync\Events\SyncFailed;
use Viovenia\GitSync\Repositories\NullHistoryRepository;
use Viovenia\GitSync\Tests\TestCase;
use Viovenia\GitSync\Widgets\RecentSyncsWidget;

final class HistoryAndWidgetTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        // A path of its own per test: the testbench storage directory is
        // shared, and one test's entries showing up in another's assertions
        // is the kind of flake that costs an afternoon.
        $this->path = sys_get_temp_dir().'/statamic-git-sync-test-'.bin2hex(random_bytes(6)).'/history.jsonl';

        $this->configure(['history.path' => $this->path]);
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }

        if (is_dir(dirname($this->path))) {
            rmdir(dirname($this->path));
        }

        parent::tearDown();
    }

    #[Test]
    public function a_commit_is_recorded_and_shows_up_in_the_widget(): void
    {
        event(new SyncCompleted($this->context(), $this->committedResult()));

        $entries = $this->history()->recent(5);

        $this->assertCount(1, $entries);
        $this->assertSame(SyncOutcome::Committed, $entries[0]->outcome);

        $html = $this->render();

        $this->assertStringContainsString('a4f2c11', $html);
        $this->assertStringContainsString('content: home.md', $html);
    }

    #[Test]
    public function a_failure_is_recorded_with_its_reason(): void
    {
        event(new SyncFailed($this->context(), SyncResultDTO::failed(
            new FailureDTO(FailureType::GitBinaryMissing, 'The git binary could not be executed.', false),
            DurationDTO::zero(),
        )));

        $entries = $this->history()->recent(5);

        $this->assertCount(1, $entries);
        $this->assertSame(SyncOutcome::Failed, $entries[0]->outcome);
        $this->assertStringContainsString('could not be executed', $entries[0]->failure ?? '');
        $this->assertStringContainsString('could not be executed', $this->render());
    }

    #[Test]
    public function a_run_that_committed_nothing_leaves_no_trace(): void
    {
        // On a quiet site the safety net alone would otherwise fill the file
        // with "nothing to commit" every fifteen minutes.
        event(new SyncCompleted($this->context(), SyncResultDTO::nothingToCommit(DurationDTO::zero())));

        $this->assertSame([], $this->history()->recent(5));
        $this->assertStringContainsString('Nothing synced yet', $this->render());
    }

    #[Test]
    #[WithConfig('statamic-git-sync.history.enabled', false)]
    public function nothing_is_recorded_while_history_is_off(): void
    {
        $this->assertInstanceOf(NullHistoryRepository::class, $this->history());

        event(new SyncCompleted($this->context(), $this->committedResult()));

        $this->assertSame([], $this->history()->recent(5));
        $this->assertStringContainsString('History is switched off', $this->render());
    }

    #[Test]
    public function the_widget_shows_at_most_what_it_was_configured_for(): void
    {
        foreach (range(1, 4) as $ignored) {
            event(new SyncCompleted($this->context(), $this->committedResult()));
        }

        $this->assertCount(4, $this->history()->recent(10));
        $this->assertSame(2, substr_count($this->render(['limit' => 2]), 'a4f2c11'));
    }

    /** @param array<string, mixed> $config */
    private function render(array $config = []): string
    {
        $widget = $this->app->make(RecentSyncsWidget::class);
        $widget->setConfig($config);

        return $widget->html()->render();
    }

    private function history(): HistoryRepositoryContract
    {
        return $this->app->make(HistoryRepositoryContract::class);
    }

    private function context(): SyncContextDTO
    {
        return SyncContextDTO::fromConsole(time());
    }

    private function committedResult(): SyncResultDTO
    {
        return SyncResultDTO::committed(
            ChangeSetDTO::of(new ChangedFileDTO('content/home.md', FileStatus::Modified)),
            new CommitReferenceDTO('a4f2c11deadbeef', 'content: home.md'),
            null,
            DurationDTO::zero(),
        );
    }
}
