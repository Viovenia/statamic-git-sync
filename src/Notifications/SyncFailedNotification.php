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
 * A sync failed and somebody should look at it.
 *
 * Sent inline rather than queued on purpose: a failure notification that
 * depends on the queue is useless exactly when the queue is what broke.
 * Nothing here runs inside a control panel request, so blocking briefly is
 * not a cost anybody pays.
 */
final class SyncFailedNotification extends Notification
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
        $failure = $this->result->failure;

        $message = (new MailMessage)
            ->error()
            ->subject('Statamic Git Sync failed')
            ->line('A content sync did not complete.')
            ->line('Trigger: '.$this->context->trigger->value)
            ->line('Reason: '.($failure !== null ? $failure->message : 'unknown'));

        if ($failure !== null) {
            $message->line($failure->retryable
                ? 'This looks temporary — the job will try again.'
                : 'This will not fix itself; the setup needs a change.');

            if ($failure->command !== null) {
                $message->line('Command: '.$failure->command->redactedCommandLine());
            }
        }

        return $message->line('Run `php artisan statamic:git-sync:doctor` for the full picture.');
    }

    public function toSlack(object $notifiable): SlackMessage
    {
        return $this->slackText(
            ':rotating_light: *Statamic Git Sync failed*',
            $this->result->failure !== null ? $this->result->failure->message : 'unknown reason',
        );
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            ...$this->context->toLogContext(),
            ...$this->result->toLogContext(),
        ];
    }
}
