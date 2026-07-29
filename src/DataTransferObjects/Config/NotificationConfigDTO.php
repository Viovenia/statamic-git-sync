<?php

declare(strict_types=1);

namespace Viovenia\GitSync\DataTransferObjects\Config;

use Viovenia\GitSync\Enums\NotificationEvent;

final readonly class NotificationConfigDTO
{
    /**
     * @param  list<NotificationEvent>  $events
     * @param  array<string, string>  $mailRecipients  address => name
     */
    public function __construct(
        public bool $enabled,
        public array $events,
        public array $mailRecipients,
        /** A Slack channel such as "#content". Needs laravel/slack-notification-channel. */
        public ?string $slackChannel,
    ) {}

    public function wants(NotificationEvent $event): bool
    {
        return $this->enabled && in_array($event, $this->events, true);
    }

    /** False when notifications are on but nobody would receive them. */
    public function hasRecipients(): bool
    {
        return $this->mailRecipients !== [] || $this->slackChannel !== null;
    }
}
