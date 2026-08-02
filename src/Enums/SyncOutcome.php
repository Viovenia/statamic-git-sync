<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Enums;

enum SyncOutcome: string
{
    /** Changes were found and committed. */
    case Committed = 'committed';

    /** The working tree held nothing inside the configured paths. */
    case NothingToCommit = 'nothing_to_commit';

    /** The run was deliberately not performed (disabled, dry run, lock held). */
    case Skipped = 'skipped';

    /** A rebase or merge conflict stopped the run; the working tree was restored. */
    case Conflicted = 'conflicted';

    /** Something went wrong that the addon could not resolve on its own. */
    case Failed = 'failed';

    public function successful(): bool
    {
        return $this === self::Committed || $this === self::NothingToCommit;
    }

    /** The translated label for the control panel; the value stays English. */
    public function label(): string
    {
        return (string) __('statamic-git-sync::enums.outcome.'.$this->value);
    }
}
