<?php

declare(strict_types=1);

namespace Viovenia\GitSync\DataTransferObjects\Config;

use Viovenia\GitSync\DataTransferObjects\Content\PathCollectionDTO;

final readonly class ContentConfigDTO
{
    /** @param list<string> $exclude */
    public function __construct(
        /** The allow list. Nothing outside these paths is ever committed. */
        public PathCollectionDTO $paths,
        /** Glob patterns applied after the allow list. */
        public array $exclude,
    ) {}
}
