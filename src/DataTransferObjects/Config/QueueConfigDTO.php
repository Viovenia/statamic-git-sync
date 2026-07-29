<?php

declare(strict_types=1);

namespace Viovenia\GitSync\DataTransferObjects\Config;

final readonly class QueueConfigDTO
{
    /** @param list<int> $backoff */
    public function __construct(
        public ?string $connection,
        public ?string $name,
        public int $tries,
        public array $backoff,
    ) {}

    /** The delay before attempt number $attempt, clamped to the last entry. */
    public function backoffFor(int $attempt): int
    {
        if ($this->backoff === []) {
            return 0;
        }

        $index = min(max($attempt, 1), count($this->backoff)) - 1;

        return $this->backoff[$index];
    }
}
