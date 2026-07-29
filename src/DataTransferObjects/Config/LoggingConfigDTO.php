<?php

declare(strict_types=1);

namespace Viovenia\GitSync\DataTransferObjects\Config;

final readonly class LoggingConfigDTO
{
    public function __construct(
        /** Null means the application's default channel. */
        public ?string $channel,
        /** PSR-3 level used for successful, unremarkable runs. */
        public string $level,
    ) {}
}
