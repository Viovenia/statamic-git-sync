# Advanced

Every moving part sits behind an interface in `Viovenia\GitSync\Contracts`, bound as a singleton in the service provider. Replacing one is a `bind()` call in your own provider — no forking, no subclassing.

## Events

Five events, each carrying DTOs rather than loose scalars, so the payload stays stable as the addon grows.

| Event | Fired when | Carries |
|---|---|---|
| `SyncStarted` | a run is about to touch the repository | `SyncContextDTO` |
| `SyncCompleted` | committed, or nothing to commit | context + `SyncResultDTO` |
| `SyncSkipped` | disabled, dry run, or another run held the lock | context + result |
| `SyncFailed` | anything went wrong | context + result |
| `ConflictDetected` | a rebase or merge conflicted and was rolled back | context + `FailureDTO` |

`ConflictDetected` is followed by `SyncFailed` — listen to whichever suits you.

```php
use Illuminate\Support\Facades\Event;
use Viovenia\GitSync\Events\ConflictDetected;

Event::listen(function (ConflictDetected $event): void {
    Notification::route('slack', config('services.slack.ops'))
        ->notify(new GitSyncConflict($event->failure->message));
});
```

Every result is inspectable without string parsing:

```php
use Viovenia\GitSync\Events\SyncCompleted;

Event::listen(function (SyncCompleted $event): void {
    $event->result->outcome;                 // SyncOutcome enum
    $event->result->changes->count();        // int
    $event->result->commit?->shortSha();     // string|null
    $event->result->push?->describe();       // 'origin main'
    $event->result->duration->inMilliseconds();
});
```

## Triggering a sync yourself

```php
use Viovenia\GitSync\Facades\GitSync;
use Viovenia\GitSync\DataTransferObjects\Sync\SyncContextDTO;

$result = GitSync::sync(SyncContextDTO::fromConsole(time()));
```

`sync()` never throws — every failure comes back as a result with a `FailureDTO`. The only exception is a conflict when `pull.on_conflict` is set to `throw`, which is what that setting asks for.

To go through the queue and the debounce window instead:

```php
use Viovenia\GitSync\Contracts\SyncDispatcherContract;

app(SyncDispatcherContract::class)->dispatch(
    SyncContextDTO::fromContentEvent(null, time())
);
```

## Your own commit messages

```php
use Viovenia\GitSync\Contracts\CommitMessageBuilderContract;

final class TicketAwareCommitMessageBuilder implements CommitMessageBuilderContract
{
    public function build(ChangeSetDTO $changes, SyncContextDTO $context): string
    {
        return sprintf('[CMS] %d file(s) by %s', $changes->count(), $context->author?->name ?? 'system');
    }
}
```

```php
// In your AppServiceProvider::register()
$this->app->singleton(CommitMessageBuilderContract::class, TicketAwareCommitMessageBuilder::class);
```

## Your own path rules

`PathFilterContract` has one method:

```php
final class OnlyPublishedEntriesFilter implements PathFilterContract
{
    public function matches(string $path): bool
    {
        return str_starts_with($path, 'content/') && ! str_contains($path, '/drafts/');
    }
}
```

The allow list still applies first — git is only ever asked about `content.paths`. This filter is the second gate, not a way to widen the first.

## Your own sync strategy

This is the seam the pull-request mode on the roadmap will use.

```php
use Viovenia\GitSync\Contracts\SyncStrategyContract;

final class PullRequestSyncStrategy implements SyncStrategyContract
{
    public function identifier(): string
    {
        return 'pull-request';
    }

    public function execute(SyncContextDTO $context): SyncResultDTO
    {
        // branch, commit, push, open a PR
    }
}
```

Register it and select it:

```php
$this->app->extend(SyncStrategyResolverContract::class, function (SyncStrategyResolver $resolver) {
    $resolver->register($this->app->make(PullRequestSyncStrategy::class));

    return $resolver;
});
```

```env
STATAMIC_GIT_SYNC_STRATEGY=pull-request
```

Throw from `execute()` rather than catching: `SyncManager` owns the failure policy, the events and the logging. A strategy that swallows its own errors makes all three impossible. Throw a `GitSyncException` subclass so the retry decision is made from the failure type rather than guessed.

## Your own health checks

```php
use Viovenia\GitSync\Contracts\HealthCheckContract;

final class DeployKeyCheck implements HealthCheckContract
{
    public function id(): string { return 'deploy-key'; }
    public function title(): string { return 'Deploy key'; }

    public function run(): HealthCheckResultDTO
    {
        return is_readable('/home/deploy/.ssh/id_deploy')
            ? HealthCheckResultDTO::ok($this->id(), $this->title(), 'readable')
            : HealthCheckResultDTO::error($this->id(), $this->title(), 'not readable by the PHP user', 'chown it.');
    }
}
```

Extend the runner the same way as the strategy resolver. Checks must not throw — the runner turns an exception into an error result so one broken check cannot hide the rest, but a check that fails cleanly reports better.

## The layers, and what each one owns

| Contract | Responsibility |
|---|---|
| `SyncManagerContract` | lock, events, logging, failure policy. Never throws. |
| `SyncStrategyContract` | the actual sequence. Throws freely. |
| `GitRepositoryContract` | git verbs. Knows nothing about Statamic. |
| `ProcessRunnerContract` | the only place a process is started. |
| `SyncDispatcherContract` | debounce marker plus queue dispatch. Does no work. |
| `DebounceRepositoryContract` | when the last change happened. A hint, never truth. |
| `SyncLockContract` | one git sequence at a time, across queue, console and scheduler. |

## Testing against this addon

`ProcessRunner` is the only class that touches the `Process` facade, so faking it covers everything:

```php
Process::preventStrayProcesses();
Process::fake(['*' => Process::result('')]);

GitSync::sync(SyncContextDTO::fromConsole(time()));

Process::assertRan(fn (PendingProcess $process): bool => $process->command[1] === 'status');
```
