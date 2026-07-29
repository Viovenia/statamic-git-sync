<?php

declare(strict_types=1);

namespace Viovenia\GitSync\DataTransferObjects\Diagnostics;

use Viovenia\GitSync\Enums\Severity;

final readonly class HealthReportDTO
{
    /** @param list<HealthCheckResultDTO> $results */
    private function __construct(public array $results) {}

    /** @param list<HealthCheckResultDTO> $results */
    public static function fromResults(array $results): self
    {
        return new self(array_values($results));
    }

    /** @return list<HealthCheckResultDTO> */
    public function withSeverity(Severity $severity): array
    {
        return array_values(array_filter(
            $this->results,
            static fn (HealthCheckResultDTO $result): bool => $result->severity === $severity,
        ));
    }

    public function hasErrors(): bool
    {
        return $this->withSeverity(Severity::Error) !== [];
    }

    public function hasWarnings(): bool
    {
        return $this->withSeverity(Severity::Warning) !== [];
    }

    public function worstSeverity(): Severity
    {
        $worst = Severity::Ok;

        foreach ($this->results as $result) {
            if ($result->severity->weight() > $worst->weight()) {
                $worst = $result->severity;
            }
        }

        return $worst;
    }

    public function count(): int
    {
        return count($this->results);
    }
}
