<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Viovenia\GitSync\Enums\FileStatus;
use Viovenia\GitSync\Services\Git\PorcelainParser;

final class PorcelainParserTest extends TestCase
{
    private PorcelainParser $parser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = new PorcelainParser;
    }

    #[Test]
    public function it_returns_an_empty_change_set_for_a_clean_tree(): void
    {
        $this->assertTrue($this->parser->parse('')->isEmpty());
    }

    #[Test]
    public function it_reads_the_common_statuses(): void
    {
        $changes = $this->parser->parse(
            "A  content/collections/pages/home.md\0"
            ." M content/collections/pages/about.md\0"
            ."D  content/globals/settings.yaml\0"
            ."?? content/collections/pages/new.md\0",
        );

        $this->assertSame(4, $changes->count());
        $this->assertSame(FileStatus::Added, $changes->files[0]->status);
        $this->assertSame(FileStatus::Modified, $changes->files[1]->status);
        $this->assertSame(FileStatus::Deleted, $changes->files[2]->status);
        $this->assertSame(FileStatus::Untracked, $changes->files[3]->status);
    }

    #[Test]
    public function it_keeps_paths_containing_spaces_and_umlauts_intact(): void
    {
        $changes = $this->parser->parse("M  content/assets/Über uns/gruppen foto.jpg\0");

        $this->assertSame('content/assets/Über uns/gruppen foto.jpg', $changes->files[0]->path);
    }

    #[Test]
    public function it_reads_the_original_path_of_a_rename(): void
    {
        $changes = $this->parser->parse("R  content/new.md\0content/old.md\0M  content/other.md\0");

        $this->assertSame(2, $changes->count());
        $this->assertSame(FileStatus::Renamed, $changes->files[0]->status);
        $this->assertSame('content/new.md', $changes->files[0]->path);
        $this->assertSame('content/old.md', $changes->files[0]->originalPath);
        $this->assertSame('content/other.md', $changes->files[1]->path);
    }

    #[Test]
    public function a_rename_reports_both_of_its_paths(): void
    {
        $changes = $this->parser->parse("R  content/new.md\0content/old.md\0");

        $this->assertSame(['content/new.md', 'content/old.md'], $changes->paths()->all());
    }

    #[Test]
    public function it_reads_conflicts_as_unmerged(): void
    {
        $changes = $this->parser->parse("UU content/collections/pages/home.md\0");

        $this->assertSame(FileStatus::Unmerged, $changes->files[0]->status);
    }
}
