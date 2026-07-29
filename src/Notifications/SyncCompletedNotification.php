<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Slack\SlackMessage;
use Viovenia\GitSync\DataTransferObjects\Sync\SyncContextDTO;
use Viovenia\GitSync\DataTransferObjects\Sync\SyncResultDTO;
use Viovenia\GitSync\Notifications\Concerns\DeliversToConfiguredChannels;

/**
 * A commit was made.
 *
 * Off by default, and worth leaving that way on any site with an active
 * editor: this is one message per debounce window, and a channel that is
 * mostly noise gets muted — which costs you the messages that mattered.
 */
final class SyncCompletedNotification extends Notification
{
    use DeliversToConfiguredChannels;

    public function __construct(
        public readonly SyncContextDTO $context,
        public readonly SyncResultDTO $result,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $this->channelsFor($notifiable);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Statamic content committed')
            ->line($this->summary());
    }

    public function toSlack(object $notifiable): SlackMessage
    {
        return $this->slackText(':white_check_mark: *Statamic content committed*', $this->summary());
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            ...$this->context->toLogContext(),
            ...$this->result->toLogContext(),
        ];
    }

    private function summary(): string
    {
        return sprintf(
            '%d file(s) committed as %s%s.',
            $this->result->changes->count(),
            $this->result->commit !== null ? $this->result->commit->shortSha() : 'unknown',
            $this->result->push !== null ? ' and pushed to '.$this->result->push->describe() : '',
        );
    }
}
