<?php

declare(strict_types=1);

namespace Viovenia\GitSync\DataTransferObjects\History;

use Viovenia\GitSync\DataTransferObjects\Content\ChangedFileDTO;
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
    /** @param list<HistoryFileDTO> $paths */
    public function __construct(
        public int $at,
        public SyncTrigger $trigger,
        public SyncOutcome $outcome,
        public int $files,
        public array $paths,
        public int $insertions,
        public int $deletions,
        public ?string $commit,
        public ?string $message,
        public ?string $pullRequestUrl,
        public ?int $pullRequestNumber,
        public ?string $failure,
        public int $durationMs,
    ) {}

    public static function fromResult(SyncContextDTO $context, SyncResultDTO $result): self
    {
        $stat = $result->commit?->stat;

        return new self(
            at: time(),
            trigger: $context->trigger,
            outcome: $result->outcome,
            files: $result->changes->count(),
            paths: array_map(
                static fn (ChangedFileDTO $file): HistoryFileDTO => HistoryFileDTO::fromChangedFile($file),
                $result->changes->files,
            ),
            insertions: $stat !== null ? $stat->insertions : 0,
            deletions: $stat !== null ? $stat->deletions : 0,
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
            'paths' => array_map(
                static fn (HistoryFileDTO $file): array => $file->toArray(),
                $this->paths,
            ),
            'insertions' => $this->insertions,
            'deletions' => $this->deletions,
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
            paths: self::paths($data),
            insertions: is_int($data['insertions'] ?? null) ? $data['insertions'] : 0,
            deletions: is_int($data['deletions'] ?? null) ? $data['deletions'] : 0,
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

    /**
     * Entries written before file paths were recorded simply have none.
     *
     * @param  array<string, mixed>  $data
     * @return list<HistoryFileDTO>
     */
    private static function paths(array $data): array
    {
        $raw = $data['paths'] ?? [];

        if (! is_array($raw)) {
            return [];
        }

        $files = [];

        foreach ($raw as $entry) {
            $file = is_array($entry) ? HistoryFileDTO::fromArray($entry) : null;

            if ($file !== null) {
                $files[] = $file;
            }
        }

        return $files;
    }

    public function stat(): string
    {
        return sprintf('+%d −%d', $this->insertions, $this->deletions);
    }

    public function hasStat(): bool
    {
        return $this->insertions > 0 || $this->deletions > 0;
    }

    /** @param array<string, mixed> $data */
    private static function nullableString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
