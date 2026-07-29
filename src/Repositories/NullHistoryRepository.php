<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Repositories;

use Viovenia\GitSync\Contracts\HistoryRepositoryContract;
use Viovenia\GitSync\DataTransferObjects\History\SyncHistoryEntryDTO;

/** What gets bound when history is switched off. Remembers nothing, costs nothing. */
final class NullHistoryRepository implements HistoryRepositoryContract
{
    public function record(SyncHistoryEntryDTO $entry): void
    {
        //
    }

    /** @return list<SyncHistoryEntryDTO> */
    public function recent(int $limit): array
    {
        return [];
    }

    public function clear(): void
    {
        //
    }

    public function enabled(): bool
    {
        return false;
    }
}
