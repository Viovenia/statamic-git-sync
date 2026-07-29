<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Contracts;

use Viovenia\GitSync\Exceptions\ConfigurationException;

interface SyncStrategyResolverContract
{
    /**
     * @throws ConfigurationException when no strategy is registered under that name
     */
    public function resolve(string $identifier): SyncStrategyContract;

    /** @return list<string> */
    public function identifiers(): array;
}
