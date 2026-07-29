<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Console\Commands;

use Illuminate\Console\Command;
use Viovenia\GitSync\Contracts\HealthCheckRunnerContract;
use Viovenia\GitSync\DataTransferObjects\Diagnostics\HealthCheckResultDTO;
use Viovenia\GitSync\Enums\Severity;

/**
 * Checks the whole setup and says what is wrong and what to do about it.
 *
 * Exits non-zero when anything is broken, so it can sit in a deploy pipeline
 * as a gate rather than as a thing somebody remembers to run.
 */
final class DoctorCommand extends Command
{
    protected $signature = 'statamic:git-sync:doctor
                            {--strict : Treat warnings as failures too}';

    protected $description = 'Check whether Statamic Git Sync is configured correctly';

    public function handle(HealthCheckRunnerContract $runner): int
    {
        $report = $runner->run();

        foreach ($report->results as $result) {
            $this->components->twoColumnDetail(
                $result->title,
                $this->badge($result).' <fg=gray>'.$result->message.'</>',
            );

            if ($result->remedy !== null) {
                $this->line('  <fg=gray>↳ '.$result->remedy.'</>');
            }
        }

        $this->newLine();

        if ($report->hasErrors()) {
            $this->components->error(count($report->withSeverity(Severity::Error)).' problem(s) need fixing.');

            return self::FAILURE;
        }

        if ($report->hasWarnings()) {
            $warnings = count($report->withSeverity(Severity::Warning));

            $this->components->warn($warnings.' warning(s); the addon will still work.');

            return $this->option('strict') ? self::FAILURE : self::SUCCESS;
        }

        $this->components->info('Everything checks out.');

        return self::SUCCESS;
    }

    private function badge(HealthCheckResultDTO $result): string
    {
        return match ($result->severity) {
            Severity::Ok => '<fg=green>OK</>',
            Severity::Warning => '<fg=yellow>WARN</>',
            Severity::Error => '<fg=red>FAIL</>',
        };
    }
}
