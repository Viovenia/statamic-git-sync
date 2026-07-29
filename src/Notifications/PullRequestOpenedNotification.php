<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Slack\SlackMessage;
use Viovenia\GitSync\DataTransferObjects\PullRequest\PullRequestDTO;
use Viovenia\GitSync\DataTransferObjects\Sync\SyncContextDTO;
use Viovenia\GitSync\Notifications\Concerns\DeliversToConfiguredChannels;

/**
 * Content changes are waiting for review.
 *
 * Fires only when a pull request was actually opened, never when commits were
 * appended to one that is already open — otherwise the reviewers would hear
 * about the same pull request every debounce window.
 */
final class PullRequestOpenedNotification extends Notification
{
    use DeliversToConfiguredChannels;

    public function __construct(
        public readonly SyncContextDTO $context,
        public readonly PullRequestDTO $pullRequest,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $this->channelsFor($notifiable);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Content changes are waiting for review')
            ->line(sprintf(
                'Pull request #%d proposes %s into %s.',
                $this->pullRequest->number,
                $this->pullRequest->head->value,
                $this->pullRequest->base->value,
            ))
            ->action('Review the changes', $this->pullRequest->url)
            ->line('Further edits will be added to this pull request rather than opening another.');
    }

    public function toSlack(object $notifiable): SlackMessage
    {
        return $this->slackText(
            ':memo: *Content changes are waiting for review*',
            sprintf(
                '<%s|#%d> proposes %s into %s.',
                $this->pullRequest->url,
                $this->pullRequest->number,
                $this->pullRequest->head->value,
                $this->pullRequest->base->value,
            ),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            ...$this->context->toLogContext(),
            'pull_request' => $this->pullRequest->describe(),
        ];
    }
}
