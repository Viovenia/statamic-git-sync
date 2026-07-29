<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Services\Diagnostics;

use Throwable;
use Viovenia\GitSync\Contracts\HealthCheckContract;
use Viovenia\GitSync\Contracts\HealthCheckRunnerContract;
use Viovenia\GitSync\DataTransferObjects\Diagnostics\HealthCheckResultDTO;
use Viovenia\GitSync\DataTransferObjects\Diagnostics\HealthReportDTO;

/**
 * Runs every registered check and collects the answers.
 *
 * A check that throws becomes an error result rather than an exception: a
 * diagnostic tool that crashes halfway through is worse than useless, because
 * it hides the checks that would have run afterwards.
 */
final class HealthCheckRunner implements HealthCheckRunnerContract
{
    /** @var list<HealthCheckContract> */
    private array $checks;

    /** @param iterable<HealthCheckContract> $checks */
    public function __construct(iterable $checks)
    {
        $this->checks = array_values([...$checks]);
    }

    public function run(): HealthReportDTO
    {
        $results = [];

        foreach ($this->checks as $check) {
            try {
                $results[] = $check->run();
            } catch (Throwable $exception) {
                $results[] = HealthCheckResultDTO::error(
                    $check->id(),
                    $check->title(),
                    'The check itself failed: '.$exception->getMessage(),
                    'This is a bug in the addon. Please report it with the message above.',
                );
            }
        }

        return HealthReportDTO::fromResults($results);
    }
}
