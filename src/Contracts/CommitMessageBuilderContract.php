<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Contracts;

use Viovenia\GitSync\DataTransferObjects\Content\ChangeSetDTO;
use Viovenia\GitSync\DataTransferObjects\Sync\SyncContextDTO;

interface CommitMessageBuilderContract
{
    /** The complete commit message, trailer included. */
    public function build(ChangeSetDTO $changes, SyncContextDTO $context): string;
}
