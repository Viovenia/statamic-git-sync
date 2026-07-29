<?php

declare(strict_types=1);

namespace Viovenia\GitSync\DataTransferObjects\Diagnostics;

use Viovenia\GitSync\Enums\Severity;

final readonly class HealthCheckResultDTO
{
    public function __construct(
        public string $id,
        public string $title,
        public Severity $severity,
        public string $message,
        /** What to do about it. Null when there is nothing to do. */
        public ?string $remedy = null,
    ) {}

    public static function ok(string $id, string $title, string $message): self
    {
        return new self($id, $title, Severity::Ok, $message);
    }

    public static function warning(string $id, string $title, string $message, string $remedy): self
    {
        return new self($id, $title, Severity::Warning, $message, $remedy);
    }

    public static function error(string $id, string $title, string $message, string $remedy): self
    {
        return new self($id, $title, Severity::Error, $message, $remedy);
    }
}
