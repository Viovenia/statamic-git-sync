<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Events;

use Viovenia\GitSync\DataTransferObjects\Sync\SyncContextDTO;

/**
 * A sync run is about to touch the repository.
 *
 * These events are the extension point for anything this addon deliberately
 * does not do — Slack notifications, metrics, deploy hooks. They carry DTOs
 * rather than loose scalars so the payload stays stable as the addon grows.
 */
final class SyncStarted
{
    public function __construct(public readonly SyncContextDTO $context) {}
}
