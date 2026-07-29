<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Contracts;

use Viovenia\GitSync\DataTransferObjects\Sync\SyncContextDTO;
use Viovenia\GitSync\DataTransferObjects\Sync\SyncResultDTO;

/**
 * How a set of content changes actually reaches the remote.
 *
 * v1 ships a single implementation that commits and optionally pushes. The
 * pull-request mode on the roadmap is a second implementation, not a rewrite
 * of the one that exists.
 */
interface SyncStrategyContract
{
    /** The value of [statamic-git-sync.strategy] that selects this one. */
    public function identifier(): string;

    /**
     * @throws \Viovenia\GitSync\Exceptions\RebaseConflictException when integrating remote work conflicts
     * @throws \Viovenia\GitSync\Exceptions\GitSyncException for every other failure
     */
    public function execute(SyncContextDTO $context): SyncResultDTO;
}
