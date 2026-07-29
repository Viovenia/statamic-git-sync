<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Slack\SlackMessage;
use Viovenia\GitSync\DataTransferObjects\Sync\FailureDTO;
use Viovenia\GitSync\DataTransferObjects\Sync\SyncContextDTO;
use Viovenia\GitSync\Notifications\Concerns\DeliversToConfiguredChannels;

/**
 * A rebase or merge conflicted and was rolled back.
 *
 * The most important message this addon sends: nothing is lost, but nothing
 * will sync again either until a person resolves the divergence by hand.
 */
final class ConflictDetectedNotification extends Notification
{
    use DeliversToConfiguredChannels;

    public function __construct(
        public readonly SyncContextDTO $context,
        public readonly FailureDTO $failure,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $this->channelsFor($notifiable);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->error()
            ->subject('Statamic Git Sync: content changes are stuck')
            ->line('Integrating remote work conflicted, so it was rolled back.')
            ->line('The working tree was left exactly as it was — nothing is lost.')
            ->line($this->failure->message)
            ->line('Content will keep piling up uncommitted until somebody resolves this:')
            ->line('`git status` and `git pull --rebase` in the repository.');
    }

    public function toSlack(object $notifiable): SlackMessage
    {
        return $this->slackText(
            ':warning: *Statamic Git Sync: content changes are stuck*',
            $this->failure->message."\nThe working tree is intact, but nothing will sync until this is resolved by hand.",
        );
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            ...$this->context->toLogContext(),
            ...$this->failure->toLogContext(),
        ];
    }
}
