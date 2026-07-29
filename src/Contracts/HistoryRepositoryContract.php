<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Contracts;

use Viovenia\GitSync\DataTransferObjects\History\SyncHistoryEntryDTO;

/**
 * Keeps the last N runs so the control panel can show what happened.
 *
 * A display, not a source of truth: git remains the record of what was
 * committed. Losing this file costs a widget its contents and nothing else,
 * which is why writing to it may never fail a sync.
 */
interface HistoryRepositoryContract
{
    public function record(SyncHistoryEntryDTO $entry): void;

    /** @return list<SyncHistoryEntryDTO> most recent first */
    public function recent(int $limit): array;

    public function clear(): void;

    public function enabled(): bool;
}
