<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Viovenia\GitSync\DataTransferObjects\Config\ContentConfigDTO;
use Viovenia\GitSync\DataTransferObjects\Content\PathCollectionDTO;
use Viovenia\GitSync\Services\Content\ContentPathFilter;

final class ContentPathFilterTest extends TestCase
{
    #[Test]
    #[DataProvider('paths')]
    public function it_decides_whether_a_path_may_be_committed(string $path, bool $expected): void
    {
        $filter = new ContentPathFilter(new ContentConfigDTO(
            paths: PathCollectionDTO::of('content', 'resources/blueprints'),
            exclude: ['*.lock', 'content/**/.DS_Store', 'content/drafts/*'],
        ));

        $this->assertSame($expected, $filter->matches($path));
    }

    /** @return array<string, array{0: string, 1: bool}> */
    public static function paths(): array
    {
        return [
            'inside content' => ['content/collections/pages/home.md', true],
            'the allowed root itself' => ['content', true],
            'a second allowed root' => ['resources/blueprints/collections/pages.yaml', true],
            'outside the allow list' => ['app/Providers/AppServiceProvider.php', false],
            'a lookalike prefix' => ['contents/collections/home.md', false],
            'excluded by extension' => ['content/collections/pages/home.lock', false],
            'excluded deep by double star' => ['content/assets/photos/.DS_Store', false],
            'double star also matches at depth zero' => ['content/.DS_Store', false],
            'single star does not cross directories' => ['content/drafts/nested/post.md', true],
            'single star inside one directory' => ['content/drafts/post.md', false],
        ];
    }

    #[Test]
    public function a_leading_slash_makes_no_difference(): void
    {
        $filter = new ContentPathFilter(new ContentConfigDTO(
            paths: PathCollectionDTO::of('/content/'),
            exclude: [],
        ));

        $this->assertTrue($filter->matches('content/collections/pages/home.md'));
    }
}
