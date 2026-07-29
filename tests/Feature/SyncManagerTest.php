<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Viovenia\GitSync\Contracts\SyncManagerContract;
use Viovenia\GitSync\Contracts\SyncStrategyContract;
use Viovenia\GitSync\Contracts\SyncStrategyResolverContract;
use Viovenia\GitSync\DataTransferObjects\Sync\DurationDTO;
use Viovenia\GitSync\DataTransferObjects\Sync\SyncContextDTO;
use Viovenia\GitSync\DataTransferObjects\Sync\SyncResultDTO;
use Viovenia\GitSync\Enums\FailureType;
use Viovenia\GitSync\Enums\PullStrategy;
use Viovenia\GitSync\Enums\SyncOutcome;
use Viovenia\GitSync\Events\ConflictDetected;
use Viovenia\GitSync\Events\SyncCompleted;
use Viovenia\GitSync\Events\SyncFailed;
use Viovenia\GitSync\Events\SyncSkipped;
use Viovenia\GitSync\Events\SyncStarted;
use Viovenia\GitSync\Exceptions\GitBinaryNotFoundException;
use Viovenia\GitSync\Exceptions\RebaseConflictException;
use Viovenia\GitSync\Services\Sync\SyncStrategyResolver;
use Viovenia\GitSync\Tests\TestCase;

final class SyncManagerTest extends TestCase
{
    #[Test]
    public function a_successful_run_fires_started_and_completed(): void
    {
        Event::fake([SyncStarted::class, SyncCompleted::class]);

        $this->withStrategy(fn (): SyncResultDTO => SyncResultDTO::nothingToCommit(DurationDTO::zero()));

        $result = $this->manager()->sync(SyncContextDTO::fromConsole(time()));

        $this->assertSame(SyncOutcome::NothingToCommit, $result->outcome);
        Event::assertDispatched(SyncStarted::class);
        Event::assertDispatched(SyncCompleted::class);
    }

    #[Test]
    public function a_failure_comes_back_as_a_result_instead_of_an_exception(): void
    {
        Event::fake([SyncFailed::class]);

        $this->withStrategy(function (): SyncResultDTO {
            throw GitBinaryNotFoundException::forBinary('git');
        });

        $result = $this->manager()->sync(SyncContextDTO::fromConsole(time()));

        $this->assertSame(SyncOutcome::Failed, $result->outcome);
        $this->assertSame(FailureType::GitBinaryMissing, $result->failure?->type);
        $this->assertFalse($result->retryable(), 'A missing binary will still be missing next time.');
        Event::assertDispatched(SyncFailed::class);
    }

    #[Test]
    public function an_unexpected_error_is_reported_as_retryable(): void
    {
        $this->withStrategy(function (): SyncResultDTO {
            throw new RuntimeException('the network went away');
        });

        $result = $this->manager()->sync(SyncContextDTO::fromConsole(time()));

        $this->assertSame(FailureType::Unknown, $result->failure?->type);
        $this->assertTrue($result->retryable());
    }

    #[Test]
    public function a_conflict_is_reported_and_announced_separately(): void
    {
        Event::fake([ConflictDetected::class, SyncFailed::class]);

        $this->withStrategy(function (): SyncResultDTO {
            throw RebaseConflictException::during(PullStrategy::Rebase);
        });

        $result = $this->manager()->sync(SyncContextDTO::fromConsole(time()));

        $this->assertSame(SyncOutcome::Conflicted, $result->outcome);
        Event::assertDispatched(ConflictDetected::class);
        Event::assertDispatched(SyncFailed::class);
    }

    #[Test]
    #[WithConfig('statamic-git-sync.pull.on_conflict', 'throw')]
    public function a_conflict_may_be_configured_to_fail_loudly(): void
    {
        $this->withStrategy(function (): SyncResultDTO {
            throw RebaseConflictException::during(PullStrategy::Rebase);
        });

        $this->expectException(RebaseConflictException::class);

        $this->manager()->sync(SyncContextDTO::fromConsole(time()));
    }

    #[Test]
    #[WithConfig('statamic-git-sync.enabled', false)]
    public function a_disabled_addon_skips_without_resolving_a_strategy(): void
    {
        Event::fake([SyncSkipped::class, SyncStarted::class]);

        $this->withStrategy(function (): SyncResultDTO {
            throw new RuntimeException('the strategy must never run');
        });

        $result = $this->manager()->sync(SyncContextDTO::fromConsole(time()));

        $this->assertSame(SyncOutcome::Skipped, $result->outcome);
        Event::assertDispatched(SyncSkipped::class);
        Event::assertNotDispatched(SyncStarted::class);
    }

    /** @param callable(SyncContextDTO): SyncResultDTO $behaviour */
    private function withStrategy(callable $behaviour): void
    {
        $strategy = new class($behaviour) implements SyncStrategyContract
        {
            /** @param callable(SyncContextDTO): SyncResultDTO $behaviour */
            public function __construct(private $behaviour) {}

            public function identifier(): string
            {
                return 'commit';
            }

            public function execute(SyncContextDTO $context): SyncResultDTO
            {
                return ($this->behaviour)($context);
            }
        };

        $this->app->instance(SyncStrategyResolverContract::class, new SyncStrategyResolver([$strategy]));
        $this->app->forgetInstance(SyncManagerContract::class);
    }

    private function manager(): SyncManagerContract
    {
        return $this->app->make(SyncManagerContract::class);
    }
}
