<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Tests\Unit;

use Illuminate\Config\Repository as ConfigRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Viovenia\GitSync\Enums\ConflictBehaviour;
use Viovenia\GitSync\Enums\ContentEvent;
use Viovenia\GitSync\Enums\PullStrategy;
use Viovenia\GitSync\Exceptions\ConfigurationException;
use Viovenia\GitSync\Support\GitSyncConfigResolver;

final class GitSyncConfigResolverTest extends TestCase
{
    #[Test]
    public function it_falls_back_to_sensible_defaults_for_an_empty_configuration(): void
    {
        $config = $this->resolve(['content' => ['paths' => ['content']]]);

        $this->assertTrue($config->enabled);
        $this->assertFalse($config->dryRun);
        $this->assertSame('commit', $config->strategy);
        $this->assertSame('git', $config->repository->gitBinary);
        $this->assertSame(60, $config->repository->timeout);
        $this->assertSame(60, $config->debounce->seconds);
        $this->assertSame(PullStrategy::Rebase, $config->pull->strategy);
        $this->assertSame(ConflictBehaviour::Abort, $config->pull->onConflict);
        $this->assertNull($config->push->branch);
        $this->assertSame([30, 120, 300], $config->queue->backoff);
    }

    #[Test]
    public function it_reads_enums_rather_than_magic_strings(): void
    {
        $config = $this->resolve([
            'content' => ['paths' => ['content']],
            'pull' => ['strategy' => 'merge', 'on_conflict' => 'throw'],
        ]);

        $this->assertSame(PullStrategy::Merge, $config->pull->strategy);
        $this->assertSame(ConflictBehaviour::Throw, $config->pull->onConflict);
    }

    #[Test]
    public function it_names_the_key_when_an_enum_value_is_unknown(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('pull.strategy');

        $this->resolve([
            'content' => ['paths' => ['content']],
            'pull' => ['strategy' => 'cherry-pick'],
        ]);
    }

    #[Test]
    public function it_rejects_an_empty_allow_list_because_it_would_never_commit(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('content.paths');

        $this->resolve(['content' => ['paths' => []]]);
    }

    #[Test]
    public function it_rejects_a_cron_expression_with_the_wrong_number_of_fields(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('safety_net.cron');

        $this->resolve([
            'content' => ['paths' => ['content']],
            'safety_net' => ['cron' => '*/15 * *'],
        ]);
    }

    #[Test]
    public function it_rejects_a_negative_timeout(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('repository.timeout');

        $this->resolve([
            'content' => ['paths' => ['content']],
            'repository' => ['timeout' => 0],
        ]);
    }

    #[Test]
    public function unknown_events_default_to_off_and_known_ones_are_read(): void
    {
        $config = $this->resolve([
            'content' => ['paths' => ['content']],
            'events' => ['entry_saved' => true],
        ]);

        $this->assertTrue($config->events->isEnabled(ContentEvent::EntrySaved));
        $this->assertFalse($config->events->isEnabled(ContentEvent::EntryDeleted));
    }

    #[Test]
    public function an_empty_branch_string_means_the_current_branch(): void
    {
        $config = $this->resolve([
            'content' => ['paths' => ['content']],
            'push' => ['branch' => ''],
        ]);

        $this->assertNull($config->push->branch);
    }

    /** @param array<string, mixed> $values */
    private function resolve(array $values): \Viovenia\GitSync\DataTransferObjects\Config\GitSyncConfigDTO
    {
        return (new GitSyncConfigResolver(new ConfigRepository([
            GitSyncConfigResolver::CONFIG_KEY => $values,
        ])))->resolve();
    }
}
