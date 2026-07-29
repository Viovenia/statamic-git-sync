<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Services\Content;

use Illuminate\Support\Carbon;
use Viovenia\GitSync\Contracts\CommitMessageBuilderContract;
use Viovenia\GitSync\DataTransferObjects\Config\CommitConfigDTO;
use Viovenia\GitSync\DataTransferObjects\Content\ChangedFileDTO;
use Viovenia\GitSync\DataTransferObjects\Content\ChangeSetDTO;
use Viovenia\GitSync\DataTransferObjects\Sync\SyncContextDTO;

/**
 * Renders the configured template and appends the trailer.
 *
 * The trailer is the loop guard: it marks every commit this addon made, so a
 * later feature (or a human running `git log --grep`) can tell automated
 * commits from hand-written ones without guessing.
 */
final class CommitMessageBuilder implements CommitMessageBuilderContract
{
    public function __construct(private readonly CommitConfigDTO $config) {}

    public function build(ChangeSetDTO $changes, SyncContextDTO $context): string
    {
        $message = strtr($this->config->messageTemplate, [
            '{summary}' => $this->summary($changes),
            '{count}' => (string) $changes->count(),
            '{user}' => $context->author !== null ? $context->author->name : 'system',
            '{date}' => Carbon::createFromTimestamp($context->requestedAt)->toDateTimeString(),
            '{files}' => implode(', ', array_map(
                static fn (ChangedFileDTO $file): string => $file->path,
                $changes->files,
            )),
        ]);

        $message = rtrim($message);

        if ($this->config->trailer === null || $this->config->trailer === '') {
            return $message;
        }

        // Blank line before trailers, per git convention.
        return $message."\n\n".$this->config->trailer;
    }

    private function summary(ChangeSetDTO $changes): string
    {
        if ($changes->isEmpty()) {
            return 'no changes';
        }

        $shown = $changes->take($this->config->summaryMaxFiles);
        $names = array_map(static fn (ChangedFileDTO $file): string => $file->basename(), $shown);
        $remaining = $changes->count() - count($shown);

        if ($remaining > 0) {
            $names[] = sprintf('and %d more', $remaining);
        }

        return implode(', ', $names);
    }
}
