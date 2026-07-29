<?php

declare(strict_types=1);

namespace Viovenia\GitSync;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Bus\Dispatcher as BusDispatcher;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Log\LogManager;
use Psr\Log\LoggerInterface;
use Statamic\Providers\AddonServiceProvider;
use Viovenia\GitSync\Console\Commands\DoctorCommand;
use Viovenia\GitSync\Console\Commands\StatusCommand;
use Viovenia\GitSync\Console\Commands\SyncCommand;
use Viovenia\GitSync\Contracts\CommitAuthorResolverContract;
use Viovenia\GitSync\Contracts\CommitMessageBuilderContract;
use Viovenia\GitSync\Contracts\DebounceRepositoryContract;
use Viovenia\GitSync\Contracts\GitRepositoryContract;
use Viovenia\GitSync\Contracts\GitRepositoryLocatorContract;
use Viovenia\GitSync\Contracts\HealthCheckContract;
use Viovenia\GitSync\Contracts\HealthCheckRunnerContract;
use Viovenia\GitSync\Contracts\PathFilterContract;
use Viovenia\GitSync\Contracts\ProcessRunnerContract;
use Viovenia\GitSync\Contracts\PullRequestClientContract;
use Viovenia\GitSync\Contracts\SyncDispatcherContract;
use Viovenia\GitSync\Contracts\SyncLockContract;
use Viovenia\GitSync\Contracts\SyncManagerContract;
use Viovenia\GitSync\Contracts\SyncStrategyResolverContract;
use Viovenia\GitSync\DataTransferObjects\Config\CommitConfigDTO;
use Viovenia\GitSync\DataTransferObjects\Config\ContentConfigDTO;
use Viovenia\GitSync\DataTransferObjects\Config\DebounceConfigDTO;
use Viovenia\GitSync\DataTransferObjects\Config\GitSyncConfigDTO;
use Viovenia\GitSync\DataTransferObjects\Config\PullConfigDTO;
use Viovenia\GitSync\DataTransferObjects\Config\PullRequestConfigDTO;
use Viovenia\GitSync\DataTransferObjects\Config\PushConfigDTO;
use Viovenia\GitSync\DataTransferObjects\Config\QueueConfigDTO;
use Viovenia\GitSync\DataTransferObjects\Config\RepositoryConfigDTO;
use Viovenia\GitSync\DataTransferObjects\Config\SafetyNetConfigDTO;
use Viovenia\GitSync\Enums\PullRequestDriver;
use Viovenia\GitSync\Repositories\DebounceRepository;
use Viovenia\GitSync\Repositories\GitRepository;
use Viovenia\GitSync\Repositories\NullDebounceRepository;
use Viovenia\GitSync\Services\Content\CommitAuthorResolver;
use Viovenia\GitSync\Services\Content\CommitMessageBuilder;
use Viovenia\GitSync\Services\Content\ContentPathFilter;
use Viovenia\GitSync\Services\Diagnostics\Checks\BranchCheck;
use Viovenia\GitSync\Services\Diagnostics\Checks\CacheLockSupportCheck;
use Viovenia\GitSync\Services\Diagnostics\Checks\ConfigurationCheck;
use Viovenia\GitSync\Services\Diagnostics\Checks\ContentPathsCheck;
use Viovenia\GitSync\Services\Diagnostics\Checks\GitBinaryCheck;
use Viovenia\GitSync\Services\Diagnostics\Checks\GitIdentityCheck;
use Viovenia\GitSync\Services\Diagnostics\Checks\PullRequestCheck;
use Viovenia\GitSync\Services\Diagnostics\Checks\QueueDriverCheck;
use Viovenia\GitSync\Services\Diagnostics\Checks\RemoteCheck;
use Viovenia\GitSync\Services\Diagnostics\Checks\RepositoryCheck;
use Viovenia\GitSync\Services\Diagnostics\Checks\SafetyNetCheck;
use Viovenia\GitSync\Services\Diagnostics\HealthCheckRunner;
use Viovenia\GitSync\Services\Git\GitCommandBuilder;
use Viovenia\GitSync\Services\Git\GitRepositoryLocator;
use Viovenia\GitSync\Services\Git\PorcelainParser;
use Viovenia\GitSync\Services\Process\ProcessRunner;
use Viovenia\GitSync\Services\PullRequest\GithubPullRequestClient;
use Viovenia\GitSync\Services\Sync\SyncDispatcher;
use Viovenia\GitSync\Services\Sync\SyncLock;
use Viovenia\GitSync\Services\Sync\SyncManager;
use Viovenia\GitSync\Services\Sync\SyncStrategyResolver;
use Viovenia\GitSync\Strategies\CommitSyncStrategy;
use Viovenia\GitSync\Strategies\PullRequestSyncStrategy;
use Viovenia\GitSync\Subscribers\ContentEventSubscriber;
use Viovenia\GitSync\Support\GitSyncConfigResolver;

/**
 * Wires every contract to its implementation, once, as a singleton.
 *
 * This is also the only class in the package allowed to read configuration:
 * everything else is handed a typed DTO. That is what makes a typo in a
 * config key fail here, at boot, instead of three layers down at runtime.
 */
final class ServiceProvider extends AddonServiceProvider
{
    /** @var list<class-string<\Illuminate\Console\Command>> */
    protected $commands = [
        SyncCommand::class,
        StatusCommand::class,
        DoctorCommand::class,
    ];

    /** @var list<class-string> */
    protected $subscribe = [
        ContentEventSubscriber::class,
    ];

    /** @var list<class-string<HealthCheckContract>> */
    private const HEALTH_CHECKS = [
        ConfigurationCheck::class,
        GitBinaryCheck::class,
        RepositoryCheck::class,
        GitIdentityCheck::class,
        BranchCheck::class,
        RemoteCheck::class,
        ContentPathsCheck::class,
        PullRequestCheck::class,
        CacheLockSupportCheck::class,
        QueueDriverCheck::class,
        SafetyNetCheck::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->registerConfiguration();
        $this->registerGit();
        $this->registerContentServices();
        $this->registerSync();
        $this->registerDiagnostics();
    }

    public function bootAddon(): void
    {
        $this->registerAboutCommand();
        $this->scheduleSafetyNet();
    }

    /**
     * The config file is merged by Statamic's AddonServiceProvider before this
     * runs, so the resolver sees the defaults plus any published overrides.
     */
    private function registerConfiguration(): void
    {
        // Statamic's AddonServiceProvider merges this too, but only once the
        // addon appears in the manifest. Merging here as well means the
        // defaults are present in a test harness and in an application that
        // has not run `statamic:install` yet.
        $this->mergeConfigFrom(
            dirname(__DIR__).'/config/statamic-git-sync.php',
            GitSyncConfigResolver::CONFIG_KEY,
        );

        $this->app->singleton(
            GitSyncConfigResolver::class,
            static fn (Application $app): GitSyncConfigResolver => new GitSyncConfigResolver(
                $app->make(ConfigRepository::class),
            ),
        );

        $this->app->singleton(
            GitSyncConfigDTO::class,
            static fn (Application $app): GitSyncConfigDTO => $app->make(GitSyncConfigResolver::class)->resolve(),
        );

        // Sections are bound individually so a class can ask for exactly the
        // slice it needs rather than the whole tree.
        $this->app->singleton(RepositoryConfigDTO::class, static fn (Application $app): RepositoryConfigDTO => $app->make(GitSyncConfigDTO::class)->repository);
        $this->app->singleton(ContentConfigDTO::class, static fn (Application $app): ContentConfigDTO => $app->make(GitSyncConfigDTO::class)->content);
        $this->app->singleton(CommitConfigDTO::class, static fn (Application $app): CommitConfigDTO => $app->make(GitSyncConfigDTO::class)->commit);
        $this->app->singleton(PullConfigDTO::class, static fn (Application $app): PullConfigDTO => $app->make(GitSyncConfigDTO::class)->pull);
        $this->app->singleton(PushConfigDTO::class, static fn (Application $app): PushConfigDTO => $app->make(GitSyncConfigDTO::class)->push);
        $this->app->singleton(PullRequestConfigDTO::class, static fn (Application $app): PullRequestConfigDTO => $app->make(GitSyncConfigDTO::class)->pullRequest);
        $this->app->singleton(DebounceConfigDTO::class, static fn (Application $app): DebounceConfigDTO => $app->make(GitSyncConfigDTO::class)->debounce);
        $this->app->singleton(QueueConfigDTO::class, static fn (Application $app): QueueConfigDTO => $app->make(GitSyncConfigDTO::class)->queue);
        $this->app->singleton(SafetyNetConfigDTO::class, static fn (Application $app): SafetyNetConfigDTO => $app->make(GitSyncConfigDTO::class)->safetyNet);
    }

    private function registerGit(): void
    {
        $this->app->singleton(ProcessRunnerContract::class, ProcessRunner::class);
        $this->app->singleton(PorcelainParser::class);

        $this->app->singleton(
            GitRepositoryLocatorContract::class,
            static fn (Application $app): GitRepositoryLocator => new GitRepositoryLocator(
                $app->make(RepositoryConfigDTO::class),
                $app->basePath(),
            ),
        );

        $this->app->singleton(
            GitCommandBuilder::class,
            static fn (Application $app): GitCommandBuilder => GitCommandBuilder::for(
                $app->make(RepositoryConfigDTO::class),
                $app->make(GitRepositoryLocatorContract::class),
            ),
        );

        $this->app->singleton(GitRepositoryContract::class, GitRepository::class);
    }

    private function registerContentServices(): void
    {
        $this->app->singleton(PathFilterContract::class, ContentPathFilter::class);
        $this->app->singleton(CommitMessageBuilderContract::class, CommitMessageBuilder::class);

        $this->app->singleton(
            CommitAuthorResolverContract::class,
            static fn (Application $app): CommitAuthorResolver => new CommitAuthorResolver(
                $app->make(AuthFactory::class),
                $app->make(CommitConfigDTO::class),
            ),
        );
    }

    private function registerSync(): void
    {
        $this->app->singleton(
            PullRequestClientContract::class,
            static fn (Application $app): PullRequestClientContract => match ($app->make(PullRequestConfigDTO::class)->driver) {
                PullRequestDriver::GitHub => new GithubPullRequestClient(
                    $app->make(HttpFactory::class),
                    $app->make(PullRequestConfigDTO::class),
                ),
            },
        );

        $this->app->singleton(
            SyncStrategyResolverContract::class,
            static fn (Application $app): SyncStrategyResolver => new SyncStrategyResolver([
                $app->make(CommitSyncStrategy::class),
                $app->make(PullRequestSyncStrategy::class),
            ]),
        );

        $this->app->singleton(
            DebounceRepositoryContract::class,
            static function (Application $app): DebounceRepositoryContract {
                $config = $app->make(DebounceConfigDTO::class);
                $cache = $app->make(CacheRepository::class);

                // Without atomic locks the marker cannot be trusted, so rather
                // than debounce badly the addon commits on every change and
                // reports the store in `statamic:git-sync:doctor`.
                if (! $config->enabled || ! $cache->getStore() instanceof LockProvider) {
                    return new NullDebounceRepository;
                }

                return new DebounceRepository($cache, $config);
            },
        );

        $this->app->singleton(
            SyncLockContract::class,
            static fn (Application $app): SyncLock => new SyncLock(
                $app->make(CacheRepository::class),
                max(60, $app->make(RepositoryConfigDTO::class)->timeout * 3),
            ),
        );

        $this->app->singleton(
            SyncManagerContract::class,
            static fn (Application $app): SyncManager => new SyncManager(
                $app->make(SyncStrategyResolverContract::class),
                $app->make(SyncLockContract::class),
                $app->make(EventDispatcher::class),
                self::logger($app),
                $app->make(GitSyncConfigDTO::class),
            ),
        );

        $this->app->singleton(
            SyncDispatcherContract::class,
            static fn (Application $app): SyncDispatcher => new SyncDispatcher(
                $app->make(BusDispatcher::class),
                $app->make(DebounceRepositoryContract::class),
                $app->make(GitSyncConfigDTO::class),
            ),
        );
    }

    private function registerDiagnostics(): void
    {
        foreach (self::HEALTH_CHECKS as $check) {
            $this->app->singleton($check);
        }

        $this->app->singleton(
            HealthCheckRunnerContract::class,
            static fn (Application $app): HealthCheckRunner => new HealthCheckRunner(array_map(
                /** @param class-string<HealthCheckContract> $check */
                static fn (string $check): HealthCheckContract => $app->make($check),
                self::HEALTH_CHECKS,
            )),
        );
    }

    /**
     * The safety net: a scheduled run that commits whatever the event driven
     * path missed. This is what turns a cache outage or a stopped worker into
     * a delay rather than a lost commit, so it is on by default.
     */
    private function scheduleSafetyNet(): void
    {
        $this->app->booted(function (): void {
            $config = $this->app->make(GitSyncConfigDTO::class);

            if (! $config->enabled || ! $config->safetyNet->enabled) {
                return;
            }

            $this->app->make(Schedule::class)
                ->command(SyncCommand::class, ['--safety-net'])
                ->cron($config->safetyNet->cron)
                ->withoutOverlapping()
                ->runInBackground();
        });
    }

    private function registerAboutCommand(): void
    {
        if (! class_exists(AboutCommand::class)) {
            return;
        }

        AboutCommand::add('Statamic Git Sync', fn (): array => $this->aboutRows());
    }

    /** @return array<string, string> */
    private function aboutRows(): array
    {
        $config = $this->app->make(GitSyncConfigDTO::class);

        return [
            'Enabled' => $config->enabled ? 'yes' : 'no',
            'Strategy' => $config->strategy,
            'Debounce' => $config->debounce->enabled ? $config->debounce->seconds.'s' : 'off',
            'Push' => $config->push->enabled ? $config->push->remote : 'off',
            'Safety net' => $config->safetyNet->enabled ? $config->safetyNet->cron : 'off',
            'Events' => (string) count($config->events->subscribable()),
        ];
    }

    /** Honours the configured log channel without every class knowing there is one. */
    private static function logger(Application $app): LoggerInterface
    {
        $channel = $app->make(GitSyncConfigDTO::class)->logging->channel;
        $logger = $app->make(LoggerInterface::class);

        return $channel !== null && $logger instanceof LogManager
            ? $logger->channel($channel)
            : $logger;
    }
}
