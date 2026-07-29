<?php

declare(strict_types=1);

namespace Viovenia\GitSync\DataTransferObjects\Git;

final readonly class CommitAuthorDTO
{
    public function __construct(
        public string $name,
        public string $email,
    ) {}

    /** The `Name <mail@example.com>` form git expects for --author. */
    public function toGitIdentity(): string
    {
        return sprintf('%s <%s>', $this->name, $this->email);
    }

    public function equals(?self $other): bool
    {
        return $other !== null
            && $this->name === $other->name
            && $this->email === $other->email;
    }
}
