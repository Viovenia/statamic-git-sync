<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Services\Git;

use Viovenia\GitSync\DataTransferObjects\Content\ChangedFileDTO;
use Viovenia\GitSync\DataTransferObjects\Content\ChangeSetDTO;
use Viovenia\GitSync\Enums\FileStatus;

/**
 * Reads the output of `git status --porcelain=v1 -z`.
 *
 * The NUL-separated form is used rather than the line based one because it
 * is the only variant that survives file names containing spaces, quotes,
 * newlines or non-ASCII characters without git escaping them. Content
 * directories are full of such names.
 *
 * Record shape: `XY <path>\0` — and for renames and copies an extra record
 * follows immediately with the original path: `RX <new>\0<old>\0`.
 */
final class PorcelainParser
{
    public function parse(string $output): ChangeSetDTO
    {
        $records = array_values(array_filter(explode("\0", $output), static fn (string $r): bool => $r !== ''));
        $files = [];

        for ($index = 0; $index < count($records); $index++) {
            $record = $records[$index];

            if (strlen($record) < 4) {
                continue;
            }

            $code = substr($record, 0, 2);
            $path = substr($record, 3);
            $status = FileStatus::fromPorcelainCode($code);

            $originalPath = null;

            if ($status === FileStatus::Renamed || $status === FileStatus::Copied) {
                // The very next record is the path the file came from.
                $originalPath = $records[$index + 1] ?? null;
                $index++;
            }

            $files[] = new ChangedFileDTO($path, $status, $originalPath);
        }

        return ChangeSetDTO::fromArray($files);
    }
}
