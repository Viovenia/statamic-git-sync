<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Enums;

enum PullRequestDriver: string
{
    case GitHub = 'github';

    /** The default API root, so only self-hosted installations need configuring. */
    public function defaultApiUrl(): string
    {
        return match ($this) {
            self::GitHub => 'https://api.github.com',
        };
    }
}
