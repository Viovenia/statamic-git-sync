<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Repositories;

use Viovenia\GitSync\Contracts\DebounceRepositoryContract;

/**
 * What gets bound when debouncing is switched off, or when the cache store
 * cannot support it.
 *
 * Nothing is remembered, so nothing is ever considered stale: every run goes
 * ahead. More commits than necessary, which is a nuisance — never fewer,
 * which would be a bug.
 */
final class NullDebounceRepository implements DebounceRepositoryContract
{
    public function touch(int $timestamp): void
    {
        //
    }

    public function latest(): ?int
    {
        return null;
    }

    public function isStale(int $timestamp): bool
    {
        return false;
    }

    public function forget(): void
    {
        //
    }

    public function enabled(): bool
    {
        return false;
    }
}
