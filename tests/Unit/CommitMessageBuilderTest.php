<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Viovenia\GitSync\DataTransferObjects\Config\CommitConfigDTO;
use Viovenia\GitSync\DataTransferObjects\Content\ChangedFileDTO;
use Viovenia\GitSync\DataTransferObjects\Content\ChangeSetDTO;
use Viovenia\GitSync\DataTransferObjects\Git\CommitAuthorDTO;
use Viovenia\GitSync\DataTransferObjects\Sync\SyncContextDTO;
use Viovenia\GitSync\Enums\FileStatus;
use Viovenia\GitSync\Services\Content\CommitMessageBuilder;

final class CommitMessageBuilderTest extends TestCase
{
    #[Test]
    public function it_lists_the_changed_files_and_appends_the_trailer(): void
    {
        $message = $this->builder()->build($this->changes(2), $this->context());

        $this->assertSame("content: home.md, about.md\n\nCommitted-by: statamic-git-sync", $message);
    }

    #[Test]
    public function it_summarises_once_there_are_more_files_than_configured(): void
    {
        $message = $this->builder()->build($this->changes(5), $this->context());

        $this->assertStringContainsString('file-0.md, file-1.md, and 3 more', $message);
    }

    #[Test]
    public function it_fills_every_known_placeholder(): void
    {
        $message = $this->builder(template: '{count} by {user} on {date}: {files}')
            ->build($this->changes(1), $this->context());

        // The date is rendered in the application timezone, so it is matched
        // by shape rather than by value.
        $this->assertMatchesRegularExpression(
            '/^1 by Marcel on \d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}: content\/home\.md/',
            $message,
        );
    }

    #[Test]
    public function it_says_system_when_no_author_was_resolved(): void
    {
        $context = new SyncContextDTO(
            trigger: $this->context()->trigger,
            author: null,
            dryRun: false,
            requestedAt: 1785326400,
        );

        $this->assertStringContainsString('system', $this->builder(template: '{user}')->build($this->changes(1), $context));
    }

    #[Test]
    public function a_null_trailer_leaves_the_message_alone(): void
    {
        $message = $this->builder(trailer: null)->build($this->changes(1), $this->context());

        $this->assertSame('content: home.md', $message);
    }

    private function builder(string $template = 'content: {summary}', ?string $trailer = 'Committed-by: statamic-git-sync'): CommitMessageBuilder
    {
        return new CommitMessageBuilder(new CommitConfigDTO(
            messageTemplate: $template,
            summaryMaxFiles: 2,
            trailer: $trailer,
            authorFromUser: true,
            fallbackAuthor: new CommitAuthorDTO('Statamic Git Sync', 'git-sync@localhost'),
            allowEmpty: false,
            sign: false,
        ));
    }

    private function context(): SyncContextDTO
    {
        // 2026-07-29 12:00:00 UTC
        return SyncContextDTO::fromContentEvent(
            new CommitAuthorDTO('Marcel', 'marcel@example.com'),
            1785326400,
        );
    }

    private function changes(int $count): ChangeSetDTO
    {
        if ($count === 1) {
            return ChangeSetDTO::of(new ChangedFileDTO('content/home.md', FileStatus::Modified));
        }

        if ($count === 2) {
            return ChangeSetDTO::of(
                new ChangedFileDTO('content/home.md', FileStatus::Modified),
                new ChangedFileDTO('content/about.md', FileStatus::Added),
            );
        }

        $files = [];

        for ($index = 0; $index < $count; $index++) {
            $files[] = new ChangedFileDTO("content/file-{$index}.md", FileStatus::Modified);
        }

        return ChangeSetDTO::fromArray($files);
    }
}
