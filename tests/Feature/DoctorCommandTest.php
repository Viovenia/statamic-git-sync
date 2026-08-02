<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Tests\Feature;

use Illuminate\Support\Facades\Process;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\Test;
use Viovenia\GitSync\Tests\TestCase;

final class DoctorCommandTest extends TestCase
{
    #[Test]
    public function it_fails_when_there_is_no_repository_to_commit_into(): void
    {
        Process::fake(['*' => Process::result('git version 2.50.1')]);

        // The testbench skeleton is not a git repository, so this is the
        // out-of-the-box failure a user would hit on a fresh install.
        $this->artisan('statamic:git-sync:doctor')
            ->assertExitCode(1);
    }

    /**
     * The people most likely to see this are on a platform that never had a
     * checkout, and they are not reading the README at that moment.
     */
    #[Test]
    public function a_missing_repository_points_at_the_deployment_shape_not_just_git_init(): void
    {
        Process::fake(['*' => Process::result('git version 2.50.1')]);

        $this->artisan('statamic:git-sync:doctor')
            ->expectsOutputToContain('persistent checkout')
            ->assertExitCode(1);
    }

    #[Test]
    public function it_reports_a_missing_git_binary_with_something_to_do_about_it(): void
    {
        Process::fake(['*' => Process::result(output: '', errorOutput: 'not found', exitCode: 127)]);

        $this->artisan('statamic:git-sync:doctor')
            ->expectsOutputToContain('Git binary')
            ->assertExitCode(1);
    }

    #[Test]
    #[WithConfig('statamic-git-sync.dry_run', true)]
    public function dry_run_is_surfaced_as_a_warning_rather_than_left_to_be_discovered(): void
    {
        Process::fake(['*' => Process::result('git version 2.50.1')]);

        $this->artisan('statamic:git-sync:doctor')
            ->expectsOutputToContain('Dry run is on');
    }

    #[Test]
    public function the_status_command_reports_the_missing_repository_too(): void
    {
        Process::fake(['*' => Process::result('')]);

        $this->artisan('statamic:git-sync:status')
            ->assertExitCode(1);
    }
}
