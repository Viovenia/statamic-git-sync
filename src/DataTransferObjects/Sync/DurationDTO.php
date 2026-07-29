<?php

declare(strict_types=1);

namespace Viovenia\GitSync\DataTransferObjects\Sync;

final readonly class DurationDTO
{
    public function __construct(public float $seconds) {}

    public static function zero(): self
    {
        return new self(0.0);
    }

    public static function ofSeconds(float $seconds): self
    {
        return new self(max(0.0, $seconds));
    }

    /** Both values come from microtime(true). */
    public static function between(float $start, float $end): self
    {
        return self::ofSeconds($end - $start);
    }

    public function inMilliseconds(): int
    {
        return (int) round($this->seconds * 1000);
    }

    public function forHumans(): string
    {
        return $this->seconds < 1.0
            ? $this->inMilliseconds().'ms'
            : number_format($this->seconds, 2).'s';
    }
}
