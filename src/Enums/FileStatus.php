<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Enums;

enum FileStatus: string
{
    case Added = 'added';
    case Modified = 'modified';
    case Deleted = 'deleted';
    case Renamed = 'renamed';
    case Copied = 'copied';
    case Untracked = 'untracked';
    case Unmerged = 'unmerged';
    case TypeChanged = 'type_changed';

    /**
     * Map a two character porcelain v1 status code onto a status.
     *
     * The code carries the index state first and the working tree state
     * second; whichever of the two is not a space wins.
     */
    public static function fromPorcelainCode(string $code): self
    {
        $index = substr($code, 0, 1);
        $worktree = substr($code, 1, 1);

        if ($index === '?' || $worktree === '?') {
            return self::Untracked;
        }

        if ($index === 'U' || $worktree === 'U' || $code === 'AA' || $code === 'DD') {
            return self::Unmerged;
        }

        $letter = $index !== ' ' && $index !== '' ? $index : $worktree;

        return match ($letter) {
            'A' => self::Added,
            'D' => self::Deleted,
            'R' => self::Renamed,
            'C' => self::Copied,
            'T' => self::TypeChanged,
            default => self::Modified,
        };
    }
}
