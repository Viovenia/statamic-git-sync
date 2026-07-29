<?php

declare(strict_types=1);

namespace Viovenia\GitSync\DataTransferObjects\Config;

final readonly class DebounceConfigDTO
{
    public function __construct(
        public bool $enabled,
        /** How long to wait after the last change before committing. */
        public int $seconds,
    ) {}

    public function delaySeconds(): int
    {
        return $this->enabled ? $this->seconds : 0;
    }
}
