<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Services\Diagnostics\Checks;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Viovenia\GitSync\Contracts\HealthCheckContract;
use Viovenia\GitSync\DataTransferObjects\Config\SafetyNetConfigDTO;
use Viovenia\GitSync\DataTransferObjects\Diagnostics\HealthCheckResultDTO;

/**
 * The safety net is what turns a cache outage into a delay instead of a lost
 * commit. If the scheduler never runs, that guarantee is gone — and nothing
 * else in the system would notice.
 */
final class SafetyNetCheck implements HealthCheckContract
{
    public const HEARTBEAT_KEY = 'statamic-git-sync:safety-net-heartbeat';

    public function __construct(
        private readonly SafetyNetConfigDTO $config,
        private readonly CacheRepository $cache,
    ) {}

    public function id(): string
    {
        return 'safety-net';
    }

    public function title(): string
    {
        return 'Safety net';
    }

    public function run(): HealthCheckResultDTO
    {
        if (! $this->config->enabled) {
            return HealthCheckResultDTO::warning(
                $this->id(),
                $this->title(),
                'Disabled. A flushed cache or a stopped queue worker can leave changes uncommitted.',
                'Set [statamic-git-sync.safety_net.enabled] to true.',
            );
        }

        $heartbeat = $this->cache->get(self::HEARTBEAT_KEY);

        if (! is_int($heartbeat)) {
            return HealthCheckResultDTO::warning(
                $this->id(),
                $this->title(),
                'Scheduled for '.$this->config->cron.', but it has never been seen to run.',
                'Make sure `php artisan schedule:run` is in the crontab of this server.',
            );
        }

        return HealthCheckResultDTO::ok(
            $this->id(),
            $this->title(),
            sprintf('%s, last run %d seconds ago', $this->config->cron, time() - $heartbeat),
        );
    }
}
