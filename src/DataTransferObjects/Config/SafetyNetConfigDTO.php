<?php

declare(strict_types=1);

namespace Viovenia\GitSync\DataTransferObjects\Config;

final readonly class SafetyNetConfigDTO
{
    public function __construct(
        public bool $enabled,
        /** Cron expression handed to the Laravel scheduler. */
        public string $cron,
    ) {}
}
