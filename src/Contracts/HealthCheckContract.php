<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Contracts;

use Viovenia\GitSync\DataTransferObjects\Diagnostics\HealthCheckResultDTO;

interface HealthCheckContract
{
    /** Stable identifier, used in output and in tests. */
    public function id(): string;

    public function title(): string;

    /** Must never throw: a check that explodes tells nobody anything. */
    public function run(): HealthCheckResultDTO;
}
