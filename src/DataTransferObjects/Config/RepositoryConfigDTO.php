<?php

declare(strict_types=1);

namespace Viovenia\GitSync\DataTransferObjects\Config;

final readonly class RepositoryConfigDTO
{
    /** @param array<string, string> $environment */
    public function __construct(
        /** Null means "the Laravel base path". */
        public ?string $path,
        public string $gitBinary,
        public int $timeout,
        public array $environment,
    ) {}
}
