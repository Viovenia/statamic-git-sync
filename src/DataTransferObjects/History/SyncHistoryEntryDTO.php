<?php

declare(strict_types=1);

namespace Viovenia\GitSync\DataTransferObjects\History;

use Viovenia\GitSync\DataTransferObjects\Sync\SyncContextDTO;
use Viovenia\GitSync\DataTransferObjects\Sync\SyncResultDTO;
use Viovenia\GitSync\Enums\SyncOutcome;
use Viovenia\GitSync\Enums\SyncTrigger;

/**
 * One line of the history file.
 *
 * Flat and scalar on purpose: this is written as JSON and read back by a
 * later release of the addon, so it has to survive round trips without
 * depending on any class that might be renamed.
 */
final readonly class SyncHistoryEntryDTO
{
    public function __construct(
        public int $at,
        public SyncTrigger $trigger,
        public SyncOutcome $outcome,
        public int $files,
        public ?string $commit,
        public ?string $message,
        public ?string $pullRequestUrl,
        public ?int $pullRequestNumber,
        public ?string $failure,
        public int $durationMs,
    ) {}

    public static function fromResult(SyncContextDTO $context, SyncResultDTO $result): self
    {
        return new self(
            at: time(),
            trigger: $context->trigger,
            outcome: $result->outcome,
            files: $result->changes->count(),
            commit: $result->commit?->shortSha(),
            message: $result->commit !== null ? $result->commit->subject : $result->reason,
            pullRequestUrl: $result->pullRequest?->url,
            pullRequestNumber: $result->pullRequest?->number,
            failure: $result->failure?->message,
            durationMs: $result->duration->inMilliseconds(),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'at' => $this->at,
            'trigger' => $this->trigger->value,
            'outcome' => $this->outcome->value,
            'files' => $this->files,
            'commit' => $this->commit,
            'message' => $this->message,
            'pull_request_url' => $this->pullRequestUrl,
            'pull_request_number' => $this->pullRequestNumber,
            'failure' => $this->failure,
            'duration_ms' => $this->durationMs,
        ];
    }

    /**
     * Reads a line back, tolerating anything an older or newer release wrote.
     *
     * Returns null rather than throwing: one unreadable line should cost that
     * line, never the whole widget.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): ?self
    {
        $trigger = SyncTrigger::tryFrom(is_string($data['trigger'] ?? null) ? $data['trigger'] : '');
        $outcome = SyncOutcome::tryFrom(is_string($data['outcome'] ?? null) ? $data['outcome'] : '');

        if ($trigger === null || $outcome === null || ! is_int($data['at'] ?? null)) {
            return null;
        }

        return new self(
            at: $data['at'],
            trigger: $trigger,
            outcome: $outcome,
            files: is_int($data['files'] ?? null) ? $data['files'] : 0,
            commit: self::nullableString($data, 'commit'),
            message: self::nullableString($data, 'message'),
            pullRequestUrl: self::nullableString($data, 'pull_request_url'),
            pullRequestNumber: is_int($data['pull_request_number'] ?? null) ? $data['pull_request_number'] : null,
            failure: self::nullableString($data, 'failure'),
            durationMs: is_int($data['duration_ms'] ?? null) ? $data['duration_ms'] : 0,
        );
    }

    public function successful(): bool
    {
        return $this->outcome->successful();
    }

    /** @param array<string, mixed> $data */
    private static function nullableString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
