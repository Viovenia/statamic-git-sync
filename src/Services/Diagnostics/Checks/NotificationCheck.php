<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Services\Diagnostics\Checks;

use Illuminate\Notifications\Slack\SlackMessage;
use Viovenia\GitSync\Contracts\HealthCheckContract;
use Viovenia\GitSync\DataTransferObjects\Config\NotificationConfigDTO;
use Viovenia\GitSync\DataTransferObjects\Diagnostics\HealthCheckResultDTO;
use Viovenia\GitSync\Enums\NotificationEvent;

/**
 * Notifications configured but undeliverable is the worst of both worlds:
 * somebody believes they are being told about failures, and they are not.
 */
final class NotificationCheck implements HealthCheckContract
{
    public function __construct(private readonly NotificationConfigDTO $config) {}

    public function id(): string
    {
        return 'notifications';
    }

    public function title(): string
    {
        return 'Notifications';
    }

    public function run(): HealthCheckResultDTO
    {
        if (! $this->config->enabled) {
            return HealthCheckResultDTO::ok($this->id(), $this->title(), 'Off.');
        }

        if (! $this->config->hasRecipients()) {
            return HealthCheckResultDTO::error(
                $this->id(),
                $this->title(),
                'Switched on, but there is neither a mail recipient nor a Slack channel.',
                'Set STATAMIC_GIT_SYNC_NOTIFY_MAIL, or switch notifications off.',
            );
        }

        if ($this->config->events === []) {
            return HealthCheckResultDTO::warning(
                $this->id(),
                $this->title(),
                'Switched on, but no events are selected, so nothing will ever be sent.',
                'Add events to [statamic-git-sync.notifications.on].',
            );
        }

        if ($this->config->slackChannel !== null && ! class_exists(SlackMessage::class)) {
            return HealthCheckResultDTO::warning(
                $this->id(),
                $this->title(),
                'A Slack channel is configured but the Slack channel package is not installed, so Slack is skipped.',
                'Run `composer require laravel/slack-notification-channel`, or remove the channel.',
            );
        }

        return HealthCheckResultDTO::ok($this->id(), $this->title(), $this->describe());
    }

    private function describe(): string
    {
        $targets = [];

        if ($this->config->mailRecipients !== []) {
            $targets[] = 'mail to '.implode(', ', array_keys($this->config->mailRecipients));
        }

        if ($this->config->slackChannel !== null) {
            $targets[] = 'slack '.$this->config->slackChannel;
        }

        return sprintf(
            '%s on %s',
            implode(' and ', $targets),
            implode(', ', array_map(
                static fn (NotificationEvent $event): string => $event->value,
                $this->config->events,
            )),
        );
    }
}
