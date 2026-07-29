<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Services\Sync;

use Viovenia\GitSync\Contracts\SyncStrategyContract;
use Viovenia\GitSync\Contracts\SyncStrategyResolverContract;
use Viovenia\GitSync\Exceptions\ConfigurationException;

/**
 * Maps the configured strategy name onto an implementation.
 *
 * Strategies are registered rather than hard-coded, which is what makes the
 * pull-request mode on the roadmap an addition instead of a rewrite: a new
 * class, one `register()` call from a service provider, one config value.
 */
final class SyncStrategyResolver implements SyncStrategyResolverContract
{
    /** @var array<string, SyncStrategyContract> */
    private array $strategies = [];

    /** @param iterable<SyncStrategyContract> $strategies */
    public function __construct(iterable $strategies = [])
    {
        foreach ($strategies as $strategy) {
            $this->register($strategy);
        }
    }

    public function register(SyncStrategyContract $strategy): void
    {
        $this->strategies[$strategy->identifier()] = $strategy;
    }

    public function resolve(string $identifier): SyncStrategyContract
    {
        return $this->strategies[$identifier]
            ?? throw ConfigurationException::unknownStrategy($identifier, implode(', ', $this->identifiers()));
    }

    /** @return list<string> */
    public function identifiers(): array
    {
        return array_keys($this->strategies);
    }
}
