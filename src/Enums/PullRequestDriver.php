<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Enums;

enum PullRequestDriver: string
{
    case GitHub = 'github';
    case GitLab = 'gitlab';

    /** The default API root, so only self-hosted installations need configuring. */
    public function defaultApiUrl(): string
    {
        return match ($this) {
            self::GitHub => 'https://api.github.com',
            self::GitLab => 'https://gitlab.com/api/v4',
        };
    }

    /** What the forge calls the thing. Used in messages people read. */
    public function proposalName(): string
    {
        return match ($this) {
            self::GitHub => 'pull request',
            self::GitLab => 'merge request',
        };
    }
}
