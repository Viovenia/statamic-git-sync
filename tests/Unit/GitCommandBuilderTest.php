<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Viovenia\GitSync\Contracts\GitRepositoryLocatorContract;
use Viovenia\GitSync\DataTransferObjects\Config\RepositoryConfigDTO;
use Viovenia\GitSync\DataTransferObjects\Content\PathCollectionDTO;
use Viovenia\GitSync\Services\Git\GitCommandBuilder;

final class GitCommandBuilderTest extends TestCase
{
    #[Test]
    public function it_puts_the_binary_first_and_keeps_argument_order(): void
    {
        $command = $this->builder()->new()->add('status', '--porcelain=v1')->build();

        $this->assertSame(['/usr/bin/git', 'status', '--porcelain=v1'], $command->commandLine());
        $this->assertSame('status', $command->verb());
    }

    #[Test]
    public function it_separates_paths_so_a_file_can_never_be_read_as_a_flag(): void
    {
        $command = $this->builder()->new()
            ->add('add', '--all')
            ->paths(PathCollectionDTO::of('content', '--not-a-flag'))
            ->build();

        $this->assertSame(
            ['/usr/bin/git', 'add', '--all', '--', 'content', '--not-a-flag'],
            $command->commandLine(),
        );
    }

    #[Test]
    public function empty_paths_add_no_separator(): void
    {
        $command = $this->builder()->new()->add('status')->paths(PathCollectionDTO::empty())->build();

        $this->assertSame(['/usr/bin/git', 'status'], $command->commandLine());
    }

    #[Test]
    public function add_if_only_appends_when_the_condition_holds(): void
    {
        $command = $this->builder()->new()
            ->add('commit')
            ->addIf(false, '--gpg-sign')
            ->addIf(true, '--allow-empty')
            ->build();

        $this->assertSame(['/usr/bin/git', 'commit', '--allow-empty'], $command->commandLine());
    }

    #[Test]
    public function each_call_returns_a_fresh_builder_so_arguments_never_leak(): void
    {
        $builder = $this->builder();

        $first = $builder->new()->add('status')->build();
        $second = $builder->new()->add('log')->build();

        $this->assertSame(['/usr/bin/git', 'status'], $first->commandLine());
        $this->assertSame(['/usr/bin/git', 'log'], $second->commandLine());
    }

    #[Test]
    public function it_redacts_credentials_when_describing_a_command(): void
    {
        $command = $this->builder()->new()
            ->add('push', 'https://marcel:ghp_abcdefghijklmnopqrstuvwxyz123@github.com/acme/site.git')
            ->build();

        $this->assertStringNotContainsString('ghp_abcdefghijklmnopqrstuvwxyz123', $command->describe());
        $this->assertStringContainsString('marcel:***@github.com', $command->describe());
    }

    private function builder(): GitCommandBuilder
    {
        $locator = new class implements GitRepositoryLocatorContract
        {
            public function path(): string
            {
                return '/srv/site';
            }

            public function exists(): bool
            {
                return true;
            }

            public function writable(): bool
            {
                return true;
            }

            public function ensureExists(): void {}
        };

        return GitCommandBuilder::for(
            new RepositoryConfigDTO(path: '/srv/site', gitBinary: '/usr/bin/git', timeout: 30, environment: []),
            $locator,
        );
    }
}
