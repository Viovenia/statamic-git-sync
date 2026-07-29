<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Notifications\Concerns;

use Illuminate\Notifications\Slack\SlackMessage;

/**
 * Works out which channels a notification can actually reach, and builds the
 * Slack payload once for all of them.
 *
 * Slack is only offered when `laravel/slack-notification-channel` is installed
 * *and* a channel is routed. Without that package Laravel cannot resolve the
 * driver, and a missing optional dependency should not turn a failed sync into
 * a failed notification on top of it.
 */
trait DeliversToConfiguredChannels
{
    /** @return list<string> */
    protected function channelsFor(object $notifiable): array
    {
        $channels = [];

        if ($this->routed($notifiable, 'mail')) {
            $channels[] = 'mail';
        }

        if ($this->routed($notifiable, 'slack') && class_exists(SlackMessage::class)) {
            $channels[] = 'slack';
        }

        return $channels;
    }

    protected function slackText(string $headline, string $detail): SlackMessage
    {
        return (new SlackMessage)->text($headline."\n".$detail);
    }

    private function routed(object $notifiable, string $channel): bool
    {
        if (! method_exists($notifiable, 'routeNotificationFor')) {
            return false;
        }

        $route = $notifiable->routeNotificationFor($channel);

        return $route !== null && $route !== [] && $route !== '';
    }
}
