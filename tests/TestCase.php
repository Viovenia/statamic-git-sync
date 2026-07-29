<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Tests;

use Illuminate\Support\Facades\Process;
use Statamic\Testing\AddonTestCase;
use Viovenia\GitSync\ServiceProvider;

abstract class TestCase extends AddonTestCase
{
    protected string $addonServiceProvider = ServiceProvider::class;

    protected function setUp(): void
    {
        parent::setUp();

        // Nothing in this package may reach the operating system except
        // through ProcessRunner. If a test ever starts a real process, that
        // is a hole in the abstraction and this turns it into a failure.
        Process::preventStrayProcesses();
    }

    /** @param array<string, mixed> $overrides */
    protected function configure(array $overrides): void
    {
        foreach ($overrides as $key => $value) {
            config()->set('statamic-git-sync.'.$key, $value);
        }

        $this->forgetResolvedConfiguration();
    }

    /** Config is resolved once into a DTO, so a change means rebuilding the graph. */
    protected function forgetResolvedConfiguration(): void
    {
        foreach ($this->app->getBindings() as $abstract => $binding) {
            if (str_starts_with((string) $abstract, 'Viovenia\\GitSync\\')) {
                $this->app->forgetInstance($abstract);
            }
        }
    }
}
