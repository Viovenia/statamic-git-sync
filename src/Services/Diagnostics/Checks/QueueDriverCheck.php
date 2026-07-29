<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Services\Diagnostics\Checks;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Viovenia\GitSync\Contracts\HealthCheckContract;
use Viovenia\GitSync\DataTransferObjects\Config\QueueConfigDTO;
use Viovenia\GitSync\DataTransferObjects\Diagnostics\HealthCheckResultDTO;

/**
 * The `sync` queue driver runs jobs inline. That makes debouncing pointless
 * and puts every git call inside the control panel request that saved an
 * entry — which is exactly what this addon exists to avoid.
 */
final class QueueDriverCheck implements HealthCheckContract
{
    public function __construct(
        private readonly ConfigRepository $config,
        private readonly QueueConfigDTO $queue,
    ) {}

    public function id(): string
    {
        return 'queue-driver';
    }

    public function title(): string
    {
        return 'Queue driver';
    }

    public function run(): HealthCheckResultDTO
    {
        $connection = $this->queue->connection ?? $this->stringConfig('queue.default', 'sync');
        $driver = $this->stringConfig("queue.connections.{$connection}.driver", $connection);

        if ($driver === 'sync') {
            return HealthCheckResultDTO::warning(
                $this->id(),
                $this->title(),
                'The sync driver runs jobs inline: no debouncing, and git runs inside the save request.',
                'Use a real queue connection (redis, database) and run `php artisan queue:work`.',
            );
        }

        return HealthCheckResultDTO::ok($this->id(), $this->title(), $connection.' ('.$driver.')');
    }

    private function stringConfig(string $key, string $default): string
    {
        $value = $this->config->get($key, $default);

        return is_string($value) ? $value : $default;
    }
}
