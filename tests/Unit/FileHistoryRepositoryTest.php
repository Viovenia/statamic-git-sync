<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Viovenia\GitSync\DataTransferObjects\Config\HistoryConfigDTO;
use Viovenia\GitSync\DataTransferObjects\History\HistoryFileDTO;
use Viovenia\GitSync\DataTransferObjects\History\SyncHistoryEntryDTO;
use Viovenia\GitSync\Enums\FileStatus;
use Viovenia\GitSync\Enums\SyncOutcome;
use Viovenia\GitSync\Enums\SyncTrigger;
use Viovenia\GitSync\Repositories\FileHistoryRepository;

final class FileHistoryRepositoryTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = sys_get_temp_dir().'/statamic-git-sync-history-'.bin2hex(random_bytes(6)).'/history.jsonl';
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
    public function it_creates_the_directory_and_writes_one_line_per_run(): void
    {
        $repository = $this->repository();

        $repository->record($this->entry(1_000));
        $repository->record($this->entry(2_000));

        $this->assertFileExists($this->path);
        $this->assertCount(2, file($this->path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []);
    }

    #[Test]
    public function the_most_recent_run_comes_back_first(): void
    {
        $repository = $this->repository();

        $repository->record($this->entry(1_000));
        $repository->record($this->entry(2_000));
        $repository->record($this->entry(3_000));

        $recent = $repository->recent(2);

        $this->assertCount(2, $recent);
        $this->assertSame(3_000, $recent[0]->at);
        $this->assertSame(2_000, $recent[1]->at);
    }

    #[Test]
    public function the_file_does_not_grow_without_bound(): void
    {
        $repository = $this->repository(maxEntries: 3);

        foreach (range(1, 10) as $index) {
            $repository->record($this->entry($index * 1_000));
        }

        $this->assertCount(3, file($this->path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []);
        $this->assertSame(10_000, $repository->recent(10)[0]->at, 'The newest entries are the ones kept.');
    }

    #[Test]
    public function reading_a_file_that_does_not_exist_yet_is_not_an_error(): void
    {
        $this->assertSame([], $this->repository()->recent(5));
    }

    #[Test]
    public function one_unreadable_line_costs_that_line_and_not_the_rest(): void
    {
        $repository = $this->repository();
        $repository->record($this->entry(1_000));

        file_put_contents($this->path, "not json at all\n", FILE_APPEND);
        file_put_contents($this->path, '{"at":"wrong type"}'."\n", FILE_APPEND);

        $repository->record($this->entry(2_000));

        $recent = $repository->recent(10);

        $this->assertCount(2, $recent);
        $this->assertSame([2_000, 1_000], array_map(static fn ($entry): int => $entry->at, $recent));
    }

    #[Test]
    public function a_path_that_cannot_be_written_never_throws(): void
    {
        // A sync must not fail because its history could not be recorded.
        $repository = new FileHistoryRepository(
            new HistoryConfigDTO(enabled: true, path: '/proc/nope/history.jsonl', maxEntries: 10),
            new NullLogger,
        );

        $repository->record($this->entry(1_000));

        $this->assertSame([], $repository->recent(5));
    }

    /**
     * A history file written by an earlier release has no `paths` key. It has
     * to keep working, because the widget reads whatever is already on disk
     * after an upgrade.
     */
    #[Test]
    public function an_entry_written_before_paths_existed_still_reads(): void
    {
        $legacy = [
            'at' => 1_785_326_400,
            'trigger' => 'content_event',
            'outcome' => 'committed',
            'files' => 2,
            'commit' => 'a4f2c11',
            'message' => 'content: home.md',
            'pull_request_url' => null,
            'pull_request_number' => null,
            'failure' => null,
            'duration_ms' => 184,
        ];

        mkdir(dirname($this->path), 0o755, true);
        file_put_contents($this->path, json_encode($legacy).PHP_EOL);

        $entry = $this->repository()->recent(1)[0] ?? null;

        $this->assertNotNull($entry);
        $this->assertSame(2, $entry->files);
        $this->assertSame([], $entry->paths);
        $this->assertFalse($entry->hasStat());
    }

    #[Test]
    public function file_paths_and_line_counts_survive_a_round_trip(): void
    {
        $repository = $this->repository();
        $repository->record($this->entry(1_000));

        $entry = $repository->recent(1)[0];

        $this->assertCount(1, $entry->paths);
        $this->assertSame('content/home.md', $entry->paths[0]->path);
        $this->assertSame(FileStatus::Modified, $entry->paths[0]->status);
        $this->assertSame('+4 −1', $entry->stat());
        $this->assertTrue($entry->hasStat());
    }

    #[Test]
    public function a_rename_keeps_both_of_its_paths(): void
    {
        $repository = $this->repository();
        $repository->record(new SyncHistoryEntryDTO(
            at: 2_000,
            trigger: SyncTrigger::Console,
            outcome: SyncOutcome::Committed,
            files: 1,
            paths: [new HistoryFileDTO('content/new.md', FileStatus::Renamed, 'content/old.md')],
            insertions: 0,
            deletions: 0,
            commit: 'a4f2c11',
            message: 'content: new.md',
            pullRequestUrl: null,
            pullRequestNumber: null,
            failure: null,
            durationMs: 5,
        ));

        $this->assertSame('content/old.md → content/new.md', $repository->recent(1)[0]->paths[0]->describe());
    }

    #[Test]
    public function a_round_trip_keeps_every_field(): void
    {
        $repository = $this->repository();
        $repository->record(new SyncHistoryEntryDTO(
            at: 1_785_326_400,
            trigger: SyncTrigger::ContentEvent,
            outcome: SyncOutcome::Committed,
            files: 3,
            paths: [
                new HistoryFileDTO('content/home.md', FileStatus::Modified),
                new HistoryFileDTO('content/new.md', FileStatus::Renamed, 'content/old.md'),
            ],
            insertions: 12,
            deletions: 3,
            commit: 'a4f2c11',
            message: 'content: home.md',
            pullRequestUrl: 'https://github.com/acme/site/pull/42',
            pullRequestNumber: 42,
            failure: null,
            durationMs: 184,
        ));

        $entry = $repository->recent(1)[0];

        $this->assertSame(SyncTrigger::ContentEvent, $entry->trigger);
        $this->assertSame(SyncOutcome::Committed, $entry->outcome);
        $this->assertSame(3, $entry->files);
        $this->assertSame('a4f2c11', $entry->commit);
        $this->assertSame('content: home.md', $entry->message);
        $this->assertSame(42, $entry->pullRequestNumber);
        $this->assertNull($entry->failure);
        $this->assertSame(184, $entry->durationMs);
        $this->assertTrue($entry->successful());
    }

    private function repository(int $maxEntries = 100): FileHistoryRepository
    {
        return new FileHistoryRepository(
            new HistoryConfigDTO(enabled: true, path: $this->path, maxEntries: $maxEntries),
            new NullLogger,
        );
    }

    private function entry(int $at): SyncHistoryEntryDTO
    {
        return new SyncHistoryEntryDTO(
            at: $at,
            trigger: SyncTrigger::Console,
            outcome: SyncOutcome::Committed,
            files: 1,
            paths: [new HistoryFileDTO('content/home.md', FileStatus::Modified)],
            insertions: 4,
            deletions: 1,
            commit: 'a4f2c11',
            message: 'content: home.md',
            pullRequestUrl: null,
            pullRequestNumber: null,
            failure: null,
            durationMs: 12,
        );
    }
}
