<?php

declare(strict_types=1);

namespace Viovenia\GitSync\DataTransferObjects\Sync;

use Viovenia\GitSync\DataTransferObjects\Content\ChangeSetDTO;
use Viovenia\GitSync\DataTransferObjects\Git\CommitReferenceDTO;
use Viovenia\GitSync\DataTransferObjects\Git\PushResultDTO;
use Viovenia\GitSync\DataTransferObjects\PullRequest\PullRequestDTO;
use Viovenia\GitSync\Enums\SyncOutcome;

final readonly class SyncResultDTO
{
    public function __construct(
        public SyncOutcome $outcome,
        public ChangeSetDTO $changes,
        public ?CommitReferenceDTO $commit = null,
        public ?PushResultDTO $push = null,
        public ?FailureDTO $failure = null,
        public ?string $reason = null,
        public DurationDTO $duration = new DurationDTO(0.0),
        /** Set by the pull-request strategy; null for a plain commit. */
        public ?PullRequestDTO $pullRequest = null,
    ) {}

    public static function committed(
        ChangeSetDTO $changes,
        CommitReferenceDTO $commit,
        ?PushResultDTO $push,
        DurationDTO $duration,
        ?PullRequestDTO $pullRequest = null,
    ): self {
        return new self(SyncOutcome::Committed, $changes, $commit, $push, null, null, $duration, $pullRequest);
    }

    public static function nothingToCommit(DurationDTO $duration): self
    {
        return new self(SyncOutcome::NothingToCommit, ChangeSetDTO::empty(), null, null, null, null, $duration);
    }

    public static function skipped(string $reason, DurationDTO $duration, ?ChangeSetDTO $changes = null): self
    {
        return new self(
            SyncOutcome::Skipped,
            $changes ?? ChangeSetDTO::empty(),
            null,
            null,
            null,
            $reason,
            $duration,
        );
    }

    public static function conflicted(FailureDTO $failure, DurationDTO $duration): self
    {
        return new self(SyncOutcome::Conflicted, ChangeSetDTO::empty(), null, null, $failure, null, $duration);
    }

    public static function failed(FailureDTO $failure, DurationDTO $duration): self
    {
        return new self(SyncOutcome::Failed, ChangeSetDTO::empty(), null, null, $failure, null, $duration);
    }

    public function successful(): bool
    {
        return $this->outcome->successful();
    }

    public function retryable(): bool
    {
        return $this->failure !== null && $this->failure->retryable;
    }

    /** @return array<string, string|int|bool|null> */
    public function toLogContext(): array
    {
        return [
            'outcome' => $this->outcome->value,
            'files' => $this->changes->count(),
            'commit' => $this->commit?->shortSha(),
            'pushed' => $this->push?->describe(),
            'pull_request' => $this->pullRequest?->describe(),
            'reason' => $this->reason,
            'duration_ms' => $this->duration->inMilliseconds(),
        ];
    }
}
