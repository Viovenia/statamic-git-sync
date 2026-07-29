<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Viovenia\GitSync\DataTransferObjects\Git\RepositorySlugDTO;
use Viovenia\GitSync\Exceptions\ConfigurationException;

final class RepositorySlugDTOTest extends TestCase
{
    #[Test]
    #[DataProvider('remoteUrls')]
    public function it_reads_owner_and_name_from_every_form_git_uses(string $url, ?string $expected): void
    {
        $slug = RepositorySlugDTO::fromRemoteUrl($url);

        $this->assertSame($expected, $slug?->toString());
    }

    /** @return array<string, array{0: string, 1: ?string}> */
    public static function remoteUrls(): array
    {
        return [
            'ssh scp form' => ['git@github.com:acme/site.git', 'acme/site'],
            'ssh without suffix' => ['git@github.com:acme/site', 'acme/site'],
            'https' => ['https://github.com/acme/site.git', 'acme/site'],
            'https with credentials' => ['https://marcel:ghp_secret@github.com/acme/site.git', 'acme/site'],
            'ssh protocol url' => ['ssh://git@github.com/acme/site.git', 'acme/site'],
            'trailing slash' => ['https://github.com/acme/site/', 'acme/site'],
            'self hosted with nesting' => ['https://git.example.com/group/sub/site.git', 'sub/site'],
            'a local path is not a slug' => ['/srv/repositories/site.git', null],
            'empty' => ['', null],
        ];
    }

    #[Test]
    public function an_explicit_slug_is_taken_as_written(): void
    {
        $this->assertSame('acme/site', RepositorySlugDTO::fromString('acme/site')->toString());
        $this->assertSame('acme/site', RepositorySlugDTO::fromString('/acme/site/')->toString());
    }

    #[Test]
    public function a_malformed_slug_names_the_config_key(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('pull_request.repository');

        RepositorySlugDTO::fromString('site');
    }
}
