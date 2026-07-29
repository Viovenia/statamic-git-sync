<?php

declare(strict_types=1);

namespace Viovenia\GitSync\DataTransferObjects\Sync;

use Viovenia\GitSync\DataTransferObjects\Git\CommitAuthorDTO;
use Viovenia\GitSync\Enums\SyncTrigger;

/**
 * Everything a sync run needs to know about why it is happening.
 *
 * This travels through the queue, so every field is a scalar, an enum or a
 * DTO made of scalars. No Eloquent models, no Statamic entries, nothing that
 * needs to be looked up again on the other side.
 */
final readonly class SyncContextDTO
{
    public function __construct(
        public SyncTrigger $trigger,
        public ?CommitAuthorDTO $author,
        public bool $dryRun,
        /** Unix timestamp of the moment that caused this run. */
        public int $requestedAt,
    ) {}

    public static function fromContentEvent(?CommitAuthorDTO $author, int $requestedAt, bool $dryRun = false): self
    {
        return new self(SyncTrigger::ContentEvent, $author, $dryRun, $requestedAt);
    }

    public static function fromConsole(int $requestedAt, bool $dryRun = false, ?CommitAuthorDTO $author = null): self
    {
        return new self(SyncTrigger::Console, $author, $dryRun, $requestedAt);
    }

    public static function fromSafetyNet(int $requestedAt): self
    {
        return new self(SyncTrigger::SafetyNet, null, false, $requestedAt);
    }

    public function withoutAuthor(): self
    {
        return new self($this->trigger, null, $this->dryRun, $this->requestedAt);
    }

    /** @return array<string, string|int|bool|null> */
    public function toLogContext(): array
    {
        return [
            'trigger' => $this->trigger->value,
            'author' => $this->author?->toGitIdentity(),
            'dry_run' => $this->dryRun,
            'requested_at' => $this->requestedAt,
        ];
    }
}
