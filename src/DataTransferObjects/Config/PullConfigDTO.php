<?php

declare(strict_types=1);

namespace Viovenia\GitSync\DataTransferObjects\Config;

use Viovenia\GitSync\Enums\ConflictBehaviour;
use Viovenia\GitSync\Enums\PullStrategy;

final readonly class PullConfigDTO
{
    public function __construct(
        public bool $enabled,
        public PullStrategy $strategy,
        public ConflictBehaviour $onConflict,
    ) {}
}
