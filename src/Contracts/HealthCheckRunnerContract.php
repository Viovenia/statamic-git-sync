<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Contracts;

use Viovenia\GitSync\DataTransferObjects\Diagnostics\HealthReportDTO;

interface HealthCheckRunnerContract
{
    public function run(): HealthReportDTO;
}
