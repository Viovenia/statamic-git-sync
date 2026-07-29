<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Contracts;

/**
 * Remembers when the most recent content change happened.
 *
 * This is a timing hint, never a source of truth: whatever it forgets, the
 * working tree still knows, and the safety net still finds.
 */
interface DebounceRepositoryContract
{
    /** Record that something changed at this unix timestamp. */
    public function touch(int $timestamp): void;

    public function latest(): ?int;

    /**
     * Whether a run scheduled for $timestamp has been superseded by a newer
     * change and should therefore step aside.
     */
    public function isStale(int $timestamp): bool;

    public function forget(): void;

    /** False when the cache store cannot support debouncing at all. */
    public function enabled(): bool;
}
