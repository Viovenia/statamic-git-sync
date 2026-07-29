<?php

declare(strict_types=1);

namespace Viovenia\GitSync\DataTransferObjects\Config;

final readonly class HistoryConfigDTO
{
    public function __construct(
        public bool $enabled,
        /** Absolute path to the JSONL file. */
        public string $path,
        /** Older lines are dropped once the file grows past this. */
        public int $maxEntries,
    ) {}
}
