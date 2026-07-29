<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Tests\Feature;

use Illuminate\Queue\Middleware\Skip;
use Illuminate\Queue\Middleware\ThrottlesExceptions;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use PHPUnit\Framework\Attributes\Test;
use Viovenia\GitSync\Contracts\DebounceRepositoryContract;
use Viovenia\GitSync\Contracts\SyncManagerContract;
use Viovenia\GitSync\DataTransferObjects\Config\GitSyncConfigDTO;
use Viovenia\GitSync\DataTransferObjects\Config\QueueConfigDTO;
use Viovenia\GitSync\DataTransferObjects\Sync\DurationDTO;
use Viovenia\GitSync\DataTransferObjects\Sync\SyncContextDTO;
use Viovenia\GitSync\DataTransferObjects\Sync\SyncJobOptionsDTO;
use Viovenia\GitSync\DataTransferObjects\Sync\SyncResultDTO;
use Viovenia\GitSync\Jobs\SyncContentJob;
use Viovenia\GitSync\Tests\TestCase;

final class SyncContentJobTest extends TestCase
{
    #[Test]
    public function a_superseded_run_steps_aside_without_touching_git(): void
    {
        $manager = $this->recordingManager();
        $debounce = $this->app->make(DebounceRepositoryContract::class);

        // A newer change arrived while this job sat in the queue.
        $debounce->touch(2_000);

        $this->job(requestedAt: 1_000)->handle($manager, $debounce, $this->app->make(QueueConfigDTO::class));

        $this->assertSame(0, $manager->calls, 'The later run will pick these changes up.');
    }

    #[Test]
    public function the_run_matching_the_marker_goes_ahead(): void
    {
        $manager = $this->recordingManager();
        $debounce = $this->app->make(DebounceRepositoryContract::class);

        $debounce->touch(2_000);

        $this->job(requestedAt: 2_000)->handle($manager, $debounce, $this->app->make(QueueConfigDTO::class));

        $this->assertSame(1, $manager->calls);
    }

    #[Test]
    public function it_carries_the_middleware_that_keeps_two_runs_apart(): void
    {
        $middleware = $this->job()->middleware();

        $this->assertCount(3, $middleware);
        $this->assertInstanceOf(Skip::class, $middleware[0]);
        $this->assertInstanceOf(WithoutOverlapping::class, $middleware[1]);
        $this->assertInstanceOf(ThrottlesExceptions::class, $middleware[2]);
    }

    #[Test]
    public function it_survives_a_round_trip_through_the_queue(): void
    {
        $job = $this->job(requestedAt: 1_234);

        /** @var SyncContentJob $revived */
        $revived = unserialize(serialize($job));

        $this->assertSame(1_234, $revived->context->requestedAt);
        $this->assertSame($job->context->trigger, $revived->context->trigger);
        $this->assertSame($job->options->tries, $revived->options->tries);
    }

    private function job(int $requestedAt = 1_000): SyncContentJob
    {
        return new SyncContentJob(
            SyncContextDTO::fromContentEvent(null, $requestedAt),
            SyncJobOptionsDTO::fromConfig($this->app->make(GitSyncConfigDTO::class)),
        );
    }

    private function recordingManager(): SyncManagerContract
    {
        return new class implements SyncManagerContract
        {
            public int $calls = 0;

            public function sync(SyncContextDTO $context): SyncResultDTO
            {
                $this->calls++;

                return SyncResultDTO::nothingToCommit(DurationDTO::zero());
            }
        };
    }
}
