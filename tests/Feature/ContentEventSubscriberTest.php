<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Events\EntryDeleted;
use Statamic\Events\EntrySaved;
use Statamic\Events\SubmissionSaved;
use Viovenia\GitSync\Enums\SyncTrigger;
use Viovenia\GitSync\Jobs\SyncContentJob;
use Viovenia\GitSync\Subscribers\ContentEventSubscriber;
use Viovenia\GitSync\Tests\TestCase;

final class ContentEventSubscriberTest extends TestCase
{
    #[Test]
    public function it_listens_to_the_content_events_that_are_switched_on(): void
    {
        $this->assertTrue($this->listensTo(EntrySaved::class));
        $this->assertTrue($this->listensTo(EntryDeleted::class));
    }

    #[Test]
    public function an_event_switched_off_in_the_config_is_not_listened_to(): void
    {
        // Submissions are off by default: a busy contact form would otherwise
        // commit on every send. Statamic listens to this event itself, which
        // is why the assertion looks for *our* listener specifically.
        $this->assertFalse($this->listensTo(SubmissionSaved::class));
    }

    /**
     * The subscriber registers its listeners at boot, so the switch has to be
     * flipped before the container is built — hence the attribute rather than
     * a config() call inside the test body.
     */
    #[Test]
    #[WithConfig('statamic-git-sync.enabled', false)]
    public function a_disabled_addon_listens_to_nothing_at_all(): void
    {
        $this->assertFalse($this->listensTo(EntrySaved::class));
    }

    /** Whether this addon — and not somebody else — listens to the event. */
    private function listensTo(string $event): bool
    {
        /** @var array<string, array<int, mixed>> $listeners */
        $listeners = Event::getRawListeners();

        foreach ($listeners[$event] ?? [] as $listener) {
            if ($listener === [ContentEventSubscriber::class, 'handle']) {
                return true;
            }
        }

        return false;
    }

    #[Test]
    public function handling_an_event_queues_exactly_one_run(): void
    {
        Queue::fake();

        $this->subscriber()->handle();

        Queue::assertPushed(SyncContentJob::class, 1);
        Queue::assertPushed(
            SyncContentJob::class,
            fn (SyncContentJob $job): bool => $job->context->trigger === SyncTrigger::ContentEvent,
        );
    }

    #[Test]
    public function the_run_is_delayed_by_the_debounce_window(): void
    {
        Queue::fake();

        $this->subscriber()->handle();

        Queue::assertPushed(
            SyncContentJob::class,
            fn (SyncContentJob $job): bool => $job->delay === 60,
        );
    }

    #[Test]
    #[WithConfig('statamic-git-sync.debounce.enabled', false)]
    public function without_debouncing_the_run_is_queued_immediately(): void
    {
        Queue::fake();

        $this->subscriber()->handle();

        Queue::assertPushed(SyncContentJob::class, fn (SyncContentJob $job): bool => $job->delay === null);
    }

    #[Test]
    public function two_changes_in_the_window_leave_only_the_later_run_to_do_the_work(): void
    {
        Queue::fake();

        $this->subscriber()->handle();
        sleep(1);
        $this->subscriber()->handle();

        // Both are queued — the debounce marker is what makes the first one
        // stand aside when it wakes up, not the dispatcher.
        Queue::assertPushed(SyncContentJob::class, 2);

        $debounce = $this->app->make(\Viovenia\GitSync\Contracts\DebounceRepositoryContract::class);
        $jobs = collect(Queue::pushedJobs()[SyncContentJob::class])
            ->map(fn (array $pushed): SyncContentJob => $pushed['job']);

        $this->assertTrue($debounce->isStale($jobs->first()->context->requestedAt));
        $this->assertFalse($debounce->isStale($jobs->last()->context->requestedAt));
    }

    private function subscriber(): ContentEventSubscriber
    {
        return $this->app->make(ContentEventSubscriber::class);
    }
}
