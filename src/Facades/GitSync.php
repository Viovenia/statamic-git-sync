<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Facades;

use Illuminate\Support\Facades\Facade;
use Viovenia\GitSync\Contracts\SyncManagerContract;

/**
 * @method static \Viovenia\GitSync\DataTransferObjects\Sync\SyncResultDTO sync(\Viovenia\GitSync\DataTransferObjects\Sync\SyncContextDTO $context)
 *
 * @see \Viovenia\GitSync\Services\Sync\SyncManager
 */
final class GitSync extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SyncManagerContract::class;
    }
}
