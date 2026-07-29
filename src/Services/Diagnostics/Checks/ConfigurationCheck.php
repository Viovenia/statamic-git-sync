<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Services\Diagnostics\Checks;

use Viovenia\GitSync\Contracts\HealthCheckContract;
use Viovenia\GitSync\Contracts\SyncStrategyResolverContract;
use Viovenia\GitSync\DataTransferObjects\Config\GitSyncConfigDTO;
use Viovenia\GitSync\DataTransferObjects\Diagnostics\HealthCheckResultDTO;

/**
 * Catches the configuration mistakes that produce no error at all — they just
 * quietly make the addon do nothing, or the wrong thing.
 */
final class ConfigurationCheck implements HealthCheckContract
{
    private const KNOWN_PLACEHOLDERS = ['{summary}', '{count}', '{user}', '{date}', '{files}'];

    public function __construct(
        private readonly GitSyncConfigDTO $config,
        private readonly SyncStrategyResolverContract $strategies,
    ) {}

    public function id(): string
    {
        return 'configuration';
    }

    public function title(): string
    {
        return 'Configuration';
    }

    public function run(): HealthCheckResultDTO
    {
        if (! in_array($this->config->strategy, $this->strategies->identifiers(), true)) {
            return HealthCheckResultDTO::error(
                $this->id(),
                $this->title(),
                sprintf('Unknown strategy "%s".', $this->config->strategy),
                'Registered strategies: '.implode(', ', $this->strategies->identifiers()),
            );
        }

        if (! $this->config->enabled) {
            return HealthCheckResultDTO::warning(
                $this->id(),
                $this->title(),
                'The addon is switched off, so nothing will ever be committed.',
                'Set [statamic-git-sync.enabled] to true.',
            );
        }

        if ($this->config->dryRun) {
            return HealthCheckResultDTO::warning(
                $this->id(),
                $this->title(),
                'Dry run is on: changes are reported but never committed.',
                'Set [statamic-git-sync.dry_run] to false once you are happy with the paths.',
            );
        }

        if ($this->config->events->subscribable() === []) {
            return HealthCheckResultDTO::warning(
                $this->id(),
                $this->title(),
                'No content events are enabled, so nothing will ever trigger a sync.',
                'Switch on the events you care about under [statamic-git-sync.events].',
            );
        }

        $unknown = $this->unknownPlaceholders($this->config->commit->messageTemplate);

        if ($unknown !== []) {
            return HealthCheckResultDTO::warning(
                $this->id(),
                $this->title(),
                'Commit message template contains unknown placeholders: '.implode(', ', $unknown),
                'Known placeholders are '.implode(', ', self::KNOWN_PLACEHOLDERS).'.',
            );
        }

        return HealthCheckResultDTO::ok(
            $this->id(),
            $this->title(),
            sprintf(
                'strategy=%s, events=%d, debounce=%ds, push=%s',
                $this->config->strategy,
                count($this->config->events->subscribable()),
                $this->config->debounce->delaySeconds(),
                $this->config->push->enabled ? $this->config->push->remote : 'off',
            ),
        );
    }

    /** @return list<string> */
    private function unknownPlaceholders(string $template): array
    {
        preg_match_all('/\{[a-z_]+\}/', $template, $matches);

        return array_values(array_unique(array_filter(
            $matches[0],
            static fn (string $placeholder): bool => ! in_array($placeholder, self::KNOWN_PLACEHOLDERS, true),
        )));
    }
}
