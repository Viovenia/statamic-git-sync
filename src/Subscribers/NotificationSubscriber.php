<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Subscribers;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Notifications\Dispatcher as NotificationDispatcher;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Notification;
use Viovenia\GitSync\DataTransferObjects\Config\NotificationConfigDTO;
use Viovenia\GitSync\Enums\NotificationEvent;
use Viovenia\GitSync\Enums\SyncOutcome;
use Viovenia\GitSync\Events\ConflictDetected;
use Viovenia\GitSync\Events\PullRequestOpened;
use Viovenia\GitSync\Events\SyncCompleted;
use Viovenia\GitSync\Events\SyncFailed;
use Viovenia\GitSync\Notifications\ConflictDetectedNotification;
use Viovenia\GitSync\Notifications\PullRequestOpenedNotification;
use Viovenia\GitSync\Notifications\SyncCompletedNotification;
use Viovenia\GitSync\Notifications\SyncFailedNotification;

/**
 * Turns the addon's own events into notifications.
 *
 * A listener rather than something baked into SyncManager, because that is
 * exactly what the events are for — and because it means anybody who wants
 * different messages can switch this off and write their own without
 * touching the sync path.
 *
 * Only events the configuration asks for are subscribed at all, so an
 * unwanted notification costs nothing at runtime.
 */
final class NotificationSubscriber
{
    public function __construct(
        private readonly NotificationDispatcher $notifications,
        private readonly NotificationConfigDTO $config,
    ) {}

    public function subscribe(Dispatcher $events): void
    {
        if (! $this->config->enabled || ! $this->config->hasRecipients()) {
            return;
        }

        $listeners = [
            NotificationEvent::SyncFailed->value => [SyncFailed::class, 'handleSyncFailed'],
            NotificationEvent::ConflictDetected->value => [ConflictDetected::class, 'handleConflictDetected'],
            NotificationEvent::PullRequestOpened->value => [PullRequestOpened::class, 'handlePullRequestOpened'],
            NotificationEvent::SyncCompleted->value => [SyncCompleted::class, 'handleSyncCompleted'],
        ];

        foreach ($this->config->events as $event) {
            [$class, $method] = $listeners[$event->value];

            $events->listen($class, [self::class, $method]);
        }
    }

    public function handleSyncFailed(SyncFailed $event): void
    {
        // A conflict fires SyncFailed as well. Sending both messages for one
        // problem would be noise, so the conflict notification wins.
        if ($event->result->outcome === SyncOutcome::Conflicted
            && $this->config->wants(NotificationEvent::ConflictDetected)) {
            return;
        }

        $this->send(new SyncFailedNotification($event->context, $event->result));
    }

    public function handleConflictDetected(ConflictDetected $event): void
    {
        $this->send(new ConflictDetectedNotification($event->context, $event->failure));
    }

    public function handlePullRequestOpened(PullRequestOpened $event): void
    {
        $this->send(new PullRequestOpenedNotification($event->context, $event->pullRequest));
    }

    public function handleSyncCompleted(SyncCompleted $event): void
    {
        // "Nothing to commit" is not news.
        if ($event->result->changes->isEmpty()) {
            return;
        }

        $this->send(new SyncCompletedNotification($event->context, $event->result));
    }

    private function send(Notification $notification): void
    {
        $notifiable = new AnonymousNotifiable;

        if ($this->config->mailRecipients !== []) {
            $notifiable->route('mail', $this->config->mailRecipients);
        }

        if ($this->config->slackChannel !== null) {
            $notifiable->route('slack', $this->config->slackChannel);
        }

        $this->notifications->send($notifiable, $notification);
    }
}
